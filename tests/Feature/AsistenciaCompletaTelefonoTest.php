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

    public function test_completa_telefono_faltante_y_registra_auditoria_y_asistencia()
    {
        [$abogado, $session, $payload] = $this->scenario('N/D');
        $payload['telefono'] = 'Cel. 310 555 0101';

        $response = $this->post(route('asistencia.reunion.submit', $session->public_token), $payload);

        $response->assertSessionHas('info', 'Asistencia registrada y celular agregado correctamente a tu caracterización.');
        $this->assertSame('Cel. 310 555 0101', $abogado->fresh()->telefono);
        $this->assertDatabaseHas('controls', [
            'codigo_abogado' => (string) $abogado->id,
            'asistencia_session_id' => $session->id,
        ]);
        $this->assertDatabaseHas('abogado_phone_updates', [
            'abogado_id' => $abogado->id,
            'reunion_id' => $session->reunion_id,
            'asistencia_session_id' => $session->id,
            'old_phone' => 'N/D',
            'new_phone' => 'Cel. 310 555 0101',
            'source' => 'asistencia',
        ]);
    }

    public function test_rechaza_correo_incorrecto_sin_actualizar_telefono()
    {
        [$abogado, $session, $payload] = $this->scenario(null);
        $payload['correo'] = 'otro@example.com';
        $payload['telefono'] = '3105550101';

        $response = $this->post(route('asistencia.reunion.submit', $session->public_token), $payload);

        $response->assertSessionHasErrors('error');
        $this->assertNull($abogado->fresh()->telefono);
        $this->assertDatabaseCount('controls', 0);
        $this->assertDatabaseCount('abogado_phone_updates', 0);
    }

    public function test_telefono_existente_debe_coincidir_y_no_se_reemplaza()
    {
        [$abogado, $session, $payload] = $this->scenario('310 555-0101');
        $payload['telefono'] = '3200000000';

        $response = $this->post(route('asistencia.reunion.submit', $session->public_token), $payload);

        $response->assertSessionHasErrors('error');
        $this->assertSame('310 555-0101', $abogado->fresh()->telefono);
        $this->assertDatabaseCount('controls', 0);
        $this->assertDatabaseCount('abogado_phone_updates', 0);
    }

    public function test_telefono_existente_normalizado_permite_asistencia()
    {
        [$abogado, $session, $payload] = $this->scenario('310 555-0101');
        $payload['telefono'] = '3105550101';

        $response = $this->post(route('asistencia.reunion.submit', $session->public_token), $payload);

        $response->assertSessionHas('info', 'Asistencia registrada con éxito.');
        $this->assertSame('310 555-0101', $abogado->fresh()->telefono);
        $this->assertDatabaseHas('controls', [
            'codigo_abogado' => (string) $abogado->id,
            'asistencia_session_id' => $session->id,
        ]);
        $this->assertDatabaseCount('abogado_phone_updates', 0);
    }

    public function test_no_acepta_placeholder_como_telefono_nuevo()
    {
        [$abogado, $session, $payload] = $this->scenario('');
        $payload['telefono'] = 'No registra';

        $response = $this->post(route('asistencia.reunion.submit', $session->public_token), $payload);

        $response->assertSessionHasErrors('telefono');
        $this->assertSame('', $abogado->fresh()->telefono);
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

        $response = $this->post(route('asistencia.reunion.submit', $session->public_token), $payload);

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseCount('controls', 1);
    }

    private function scenario(?string $telefono): array
    {
        $abogado = Abogado::create([
            'nombre' => 'Abogada Prueba',
            'cc' => '1.030.590.916',
            'correo' => 'abogada@example.com',
            'telefono' => $telefono,
            'activo' => true,
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
            'token' => hash_hmac('sha256', $session->id . '|' . $slot, config('app.key')),
        ]];
    }
}
