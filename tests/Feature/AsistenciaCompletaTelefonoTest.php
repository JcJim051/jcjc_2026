<?php

namespace Tests\Feature;

use App\Models\Abogado;
use App\Models\AsistenciaSession;
use App\Models\Control;
use App\Models\Reunion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AsistenciaCompletaTelefonoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('abogados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('cc')->nullable();
            $table->string('correo')->nullable();
            $table->string('telefono')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('reuniones', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->string('lugar')->nullable();
            $table->string('motivo')->nullable();
            $table->timestamps();
        });
        Schema::create('asistencia_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reunion_id');
            $table->string('public_token', 80)->unique();
            $table->boolean('activa')->default(true);
            $table->timestamp('abierta_en')->nullable();
            $table->timestamp('cerrada_en')->nullable();
            $table->timestamps();
        });
        Schema::create('controls', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_abogado', 50);
            $table->unsignedBigInteger('asistencia_session_id');
            $table->dateTime('fecha')->nullable();
            $table->string('lugar')->nullable();
            $table->string('motivo')->nullable();
            $table->timestamps();
            $table->unique(['asistencia_session_id', 'codigo_abogado']);
        });
        Schema::create('abogado_reunion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('abogado_id');
            $table->unsignedBigInteger('reunion_id');
            $table->timestamps();
            $table->unique(['abogado_id', 'reunion_id']);
        });
        Schema::create('abogado_phone_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('abogado_id');
            $table->unsignedBigInteger('reunion_id');
            $table->unsignedBigInteger('asistencia_session_id');
            $table->string('old_phone')->nullable();
            $table->string('new_phone');
            $table->string('old_email')->nullable();
            $table->string('new_email')->nullable();
            $table->string('source', 50);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('abogado_phone_updates');
        Schema::dropIfExists('abogado_reunion');
        Schema::dropIfExists('controls');
        Schema::dropIfExists('asistencia_sessions');
        Schema::dropIfExists('reuniones');
        Schema::dropIfExists('abogados');

        parent::tearDown();
    }

    public function test_consulta_por_cedula_carga_datos_completos()
    {
        [$abogado, $session, $payload] = $this->scenario('3105550101');

        $response = $this->postJson(route('asistencia.reunion.lookup', $session->public_token), [
            'cc' => '1.030.590.916',
            'slot' => $payload['slot'],
            'token' => $payload['token'],
        ]);

        $response->assertOk()->assertExactJson([
            'nombre' => $abogado->nombre,
            'correo' => $abogado->correo,
            'telefono' => $abogado->telefono,
        ]);
    }

    public function test_consulta_bloquea_cedula_inexistente_o_duplicada()
    {
        [, $session, $payload] = $this->scenario('3105550101');

        $this->postJson(route('asistencia.reunion.lookup', $session->public_token), array_merge($payload, [
            'cc' => '999999999',
        ]))->assertNotFound();

        Abogado::create([
            'nombre' => 'Registro duplicado',
            'cc' => '1030590916',
            'correo' => 'duplicado@example.com',
            'telefono' => '3110000000',
            'activo' => true,
        ]);

        $this->postJson(route('asistencia.reunion.lookup', $session->public_token), array_merge($payload, [
            'cc' => '1030590916',
        ]))->assertStatus(409);
    }

    public function test_datos_sin_cambios_registran_asistencia_sin_auditoria()
    {
        [$abogado, $session, $payload] = $this->scenario('310 555-0101');
        $payload['telefono'] = '3105550101';

        $response = $this->post(route('asistencia.reunion.submit', $session->public_token), $payload);

        $response->assertSessionHas('attendance_registered', true);
        $this->assertDatabaseHas('controls', [
            'codigo_abogado' => (string) $abogado->id,
            'asistencia_session_id' => $session->id,
        ]);
        $this->assertDatabaseCount('abogado_phone_updates', 0);
    }

    public function test_actualiza_correo_y_telefono_y_registra_auditoria()
    {
        [$abogado, $session, $payload] = $this->scenario('N/D');
        $payload['correo'] = 'NUEVO@Example.com';
        $payload['telefono'] = 'Cel. 310 555 0101';

        $response = $this->post(route('asistencia.reunion.submit', $session->public_token), $payload);

        $response->assertSessionHas('info', 'Asistencia registrada y datos de contacto actualizados correctamente.');
        $abogado->refresh();
        $this->assertSame('nuevo@example.com', $abogado->correo);
        $this->assertSame('Cel. 310 555 0101', $abogado->telefono);
        $this->assertDatabaseHas('abogado_phone_updates', [
            'abogado_id' => $abogado->id,
            'reunion_id' => $session->reunion_id,
            'asistencia_session_id' => $session->id,
            'old_phone' => 'N/D',
            'new_phone' => 'Cel. 310 555 0101',
            'old_email' => 'abogada@example.com',
            'new_email' => 'nuevo@example.com',
            'source' => 'asistencia',
        ]);
    }

    public function test_correo_de_otra_persona_bloquea_cambios_y_asistencia()
    {
        [$abogado, $session, $payload] = $this->scenario('3105550101');
        Abogado::create([
            'nombre' => 'Otra persona',
            'cc' => '111111111',
            'correo' => 'ocupado@example.com',
            'telefono' => '3200000000',
            'activo' => true,
        ]);
        $payload['correo'] = 'OCUPADO@example.com';
        $payload['telefono'] = '3150000000';

        $response = $this->post(route('asistencia.reunion.submit', $session->public_token), $payload);

        $response->assertSessionHasErrors('error');
        $abogado->refresh();
        $this->assertSame('abogada@example.com', $abogado->correo);
        $this->assertSame('3105550101', $abogado->telefono);
        $this->assertDatabaseCount('controls', 0);
        $this->assertDatabaseCount('abogado_phone_updates', 0);
    }

    public function test_abogado_inactivo_puede_consultar_y_registrar_asistencia()
    {
        [$abogado, $session, $payload] = $this->scenario('3105550101', false);

        $this->postJson(route('asistencia.reunion.lookup', $session->public_token), $payload)->assertOk();
        $this->post(route('asistencia.reunion.submit', $session->public_token), $payload)
            ->assertSessionHas('attendance_registered', true);

        $this->assertDatabaseHas('controls', [
            'codigo_abogado' => (string) $abogado->id,
            'asistencia_session_id' => $session->id,
        ]);
    }

    public function test_no_acepta_placeholder_como_telefono()
    {
        [$abogado, $session, $payload] = $this->scenario('3105550101');
        $payload['telefono'] = 'No registra';

        $response = $this->post(route('asistencia.reunion.submit', $session->public_token), $payload);

        $response->assertSessionHasErrors('telefono');
        $this->assertSame('3105550101', $abogado->fresh()->telefono);
        $this->assertDatabaseCount('controls', 0);
    }

    public function test_no_permite_doble_asistencia_en_la_misma_reunion()
    {
        [$abogado, $session, $payload] = $this->scenario('3105550101');
        Control::create([
            'codigo_abogado' => (string) $abogado->id,
            'asistencia_session_id' => $session->id,
            'fecha' => now(),
        ]);

        $this->postJson(route('asistencia.reunion.lookup', $session->public_token), $payload)->assertStatus(409);
        $this->post(route('asistencia.reunion.submit', $session->public_token), $payload)
            ->assertSessionHasErrors('error');
        $this->assertDatabaseCount('controls', 1);
    }

    public function test_enlace_sin_firma_muestra_estado_vencido()
    {
        [, $session] = $this->scenario('3105550101');

        $this->get(route('asistencia.reunion.form', $session->public_token))
            ->assertStatus(410)
            ->assertSee('Este enlace ya caducó');
    }

    private function scenario(?string $telefono, bool $activo = true): array
    {
        $abogado = Abogado::create([
            'nombre' => 'Abogada Prueba',
            'cc' => '1.030.590.916',
            'correo' => 'abogada@example.com',
            'telefono' => $telefono,
            'activo' => $activo,
        ]);
        $reunion = Reunion::create([
            'fecha' => now()->toDateString(),
            'hora_inicio' => '08:00',
            'hora_fin' => '10:00',
            'lugar' => 'Sede Testiapp',
        ]);
        $session = AsistenciaSession::create([
            'reunion_id' => $reunion->id,
            'public_token' => str_repeat('a', 48),
            'activa' => true,
            'abierta_en' => now(),
        ]);
        $slot = 123456;

        return [$abogado, $session, [
            'cc' => '1030590916',
            'correo' => 'abogada@example.com',
            'telefono' => $telefono ?: '3105550101',
            'slot' => $slot,
            'token' => hash_hmac('sha256', $session->id.'|'.$slot, config('app.key')),
        ]];
    }
}
