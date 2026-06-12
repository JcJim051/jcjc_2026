<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\Referido;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicReferidosElectionDuplicateTest extends TestCase
{
    private string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('elecciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('tipo');
            $table->string('estado')->default('activa');
            $table->unsignedTinyInteger('meta_testigos_pct')->nullable();
            $table->timestamps();
        });
        Schema::create('eleccion_puestos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('eleccion_id');
            $table->string('dd', 2);
            $table->string('mm', 3);
            $table->string('zz', 2);
            $table->string('pp', 2);
            $table->string('departamento')->nullable();
            $table->string('municipio')->nullable();
            $table->string('puesto')->nullable();
            $table->string('comuna')->nullable();
            $table->unsignedInteger('mesas_total')->default(0);
            $table->timestamps();
        });
        Schema::create('eleccion_mesas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('eleccion_id');
            $table->unsignedBigInteger('eleccion_puesto_id');
            $table->unsignedInteger('mesa_num');
            $table->string('mesa_key')->unique();
            $table->timestamps();
        });
        Schema::create('territorio_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('eleccion_id');
            $table->string('dd', 2);
            $table->string('mm', 3);
            $table->string('comuna')->nullable();
            $table->boolean('es_consulta')->default(false);
            $table->json('municipios')->nullable();
            $table->string('token')->unique();
            $table->string('responsable')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->string('cedula')->nullable()->unique();
            $table->string('nombre_original')->nullable();
            $table->string('nombre_normalizado')->nullable();
            $table->string('nombre')->nullable();
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->timestamps();
        });
        Schema::create('referidos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('persona_id');
            $table->unsignedBigInteger('eleccion_id');
            $table->unsignedBigInteger('eleccion_puesto_id');
            $table->unsignedBigInteger('territorio_token_id');
            $table->unsignedInteger('mesa_num');
            $table->string('cedula_pdf_path')->nullable();
            $table->string('estado')->default('referido');
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->unique(['persona_id', 'eleccion_id']);
        });
        Schema::create('mesa_bloqueos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('eleccion_id');
            $table->unsignedBigInteger('eleccion_puesto_id');
            $table->unsignedInteger('mesa_num');
            $table->timestamps();
        });
        Schema::create('eleccion_puesto_metas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('eleccion_id');
            $table->unsignedBigInteger('eleccion_puesto_id');
            $table->unsignedInteger('meta_pactada');
            $table->timestamps();
        });

        $this->storageRoot = sys_get_temp_dir() . '/testiapp-public-referidos-tests-' . uniqid('', true);
        config(['filesystems.disks.public.root' => $this->storageRoot]);
        Storage::forgetDisk('public');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storageRoot);
        Schema::dropIfExists('eleccion_puesto_metas');
        Schema::dropIfExists('mesa_bloqueos');
        Schema::dropIfExists('referidos');
        Schema::dropIfExists('personas');
        Schema::dropIfExists('territorio_tokens');
        Schema::dropIfExists('eleccion_mesas');
        Schema::dropIfExists('eleccion_puestos');
        Schema::dropIfExists('elecciones');

        parent::tearDown();
    }

    public function test_persona_de_eleccion_anterior_puede_registrarse_en_la_actual()
    {
        $anterior = $this->createElectionContext('Elección anterior', 'cerrada', 'token-anterior', 1);
        $actual = $this->createElectionContext('Segunda Vuelta', 'activa', 'token-segunda', 1);
        $persona = Persona::create([
            'cedula' => '1.234.567',
            'nombre' => 'Nombre anterior',
            'nombre_original' => 'Nombre anterior',
            'nombre_normalizado' => 'nombre anterior',
            'email' => 'anterior@example.com',
            'telefono' => '3000000000',
        ]);
        Referido::create([
            'persona_id' => $persona->id,
            'eleccion_id' => $anterior['eleccion_id'],
            'eleccion_puesto_id' => $anterior['puesto_id'],
            'territorio_token_id' => $anterior['token_id'],
            'mesa_num' => 1,
            'cedula_pdf_path' => 'cedulas/anterior.pdf',
            'estado' => 'validado',
        ]);

        $response = $this->post(route('public.referidos.store', $actual['token']), $this->payload([
            'cedula' => '1 234-567',
            'nombre' => 'Nombre actualizado',
            'email' => 'actualizado@example.com',
            'telefono' => '3111111111',
            'puesto_id' => $actual['puesto_id'],
        ]));

        $response->assertRedirect(route('public.referidos.form', $actual['token']))
            ->assertSessionHas('success');
        $this->assertDatabaseCount('personas', 1);
        $this->assertDatabaseCount('referidos', 2);
        $this->assertDatabaseHas('personas', [
            'id' => $persona->id,
            'nombre' => 'Nombre actualizado',
            'email' => 'actualizado@example.com',
            'telefono' => '3111111111',
        ]);
        $this->assertDatabaseHas('referidos', [
            'persona_id' => $persona->id,
            'eleccion_id' => $actual['eleccion_id'],
            'territorio_token_id' => $actual['token_id'],
            'estado' => 'referido',
        ]);
    }

    public function test_rechazado_de_la_misma_eleccion_continua_bloqueado()
    {
        $actual = $this->createElectionContext('Segunda Vuelta', 'activa', 'token-rechazado', 1);
        $persona = Persona::create([
            'cedula' => '1234567',
            'nombre' => 'Persona rechazada',
            'email' => 'rechazada@example.com',
            'telefono' => '3000000000',
        ]);
        Referido::create([
            'persona_id' => $persona->id,
            'eleccion_id' => $actual['eleccion_id'],
            'eleccion_puesto_id' => $actual['puesto_id'],
            'territorio_token_id' => $actual['token_id'],
            'mesa_num' => 1,
            'cedula_pdf_path' => 'cedulas/rechazado.pdf',
            'estado' => 'rechazado',
        ]);

        $response = $this->from(route('public.referidos.form', $actual['token']))
            ->post(route('public.referidos.store', $actual['token']), $this->payload([
                'cedula' => '1.234.567',
                'puesto_id' => $actual['puesto_id'],
            ]));

        $response->assertRedirect(route('public.referidos.form', $actual['token']))
            ->assertSessionHasErrors([
                'cedula' => 'Esta persona ya está registrada en esta elección. Puedes editar o reactivar el registro existente.',
            ]);
        $this->assertDatabaseCount('personas', 1);
        $this->assertDatabaseCount('referidos', 1);
        Storage::disk('public')->assertMissing('cedulas');
    }

    public function test_correo_de_otra_persona_bloquea_el_registro()
    {
        $actual = $this->createElectionContext('Segunda Vuelta', 'activa', 'token-correo', 1);
        Persona::create([
            'cedula' => '9999999',
            'nombre' => 'Dueña del correo',
            'email' => 'ocupado@example.com',
            'telefono' => '3000000000',
        ]);

        $response = $this->from(route('public.referidos.form', $actual['token']))
            ->post(route('public.referidos.store', $actual['token']), $this->payload([
                'cedula' => '1234567',
                'email' => 'OCUPADO@example.com',
                'puesto_id' => $actual['puesto_id'],
            ]));

        $response->assertSessionHasErrors([
            'email' => 'El correo pertenece a otra persona registrada. Verifica la información.',
        ]);
        $this->assertDatabaseCount('personas', 1);
        $this->assertDatabaseCount('referidos', 0);
    }

    public function test_persona_nueva_se_crea_con_cedula_normalizada()
    {
        $actual = $this->createElectionContext('Segunda Vuelta', 'activa', 'token-nuevo', 1);

        $this->post(route('public.referidos.store', $actual['token']), $this->payload([
            'cedula' => '1.234,567-8',
            'puesto_id' => $actual['puesto_id'],
        ]))->assertSessionHas('success');

        $this->assertDatabaseHas('personas', ['cedula' => '12345678']);
        $this->assertDatabaseHas('referidos', ['eleccion_id' => $actual['eleccion_id']]);
    }

    private function createElectionContext(string $nombre, string $estado, string $token, int $mesa): array
    {
        $eleccionId = DB::table('elecciones')->insertGetId([
            'nombre' => $nombre,
            'tipo' => 'presidencia',
            'estado' => $estado,
            'meta_testigos_pct' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $puestoId = DB::table('eleccion_puestos')->insertGetId([
            'eleccion_id' => $eleccionId,
            'dd' => '50',
            'mm' => '001',
            'zz' => '01',
            'pp' => str_pad((string) $eleccionId, 2, '0', STR_PAD_LEFT),
            'departamento' => 'META',
            'municipio' => 'VILLAVICENCIO',
            'puesto' => 'PUESTO DE PRUEBA',
            'comuna' => null,
            'mesas_total' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('eleccion_mesas')->insert([
            'eleccion_id' => $eleccionId,
            'eleccion_puesto_id' => $puestoId,
            'mesa_num' => $mesa,
            'mesa_key' => $eleccionId . '-' . $puestoId . '-' . $mesa,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tokenId = DB::table('territorio_tokens')->insertGetId([
            'eleccion_id' => $eleccionId,
            'dd' => '50',
            'mm' => '001',
            'comuna' => null,
            'es_consulta' => false,
            'municipios' => null,
            'token' => $token,
            'responsable' => 'Prueba',
            'activo' => true,
            'expires_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'eleccion_id' => $eleccionId,
            'puesto_id' => $puestoId,
            'token_id' => $tokenId,
            'token' => $token,
        ];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'cedula' => '1234567',
            'nombre' => 'Persona de prueba',
            'email' => 'persona@example.com',
            'telefono' => '3100000000',
            'mesa_num' => 1,
            'cedula_pdf' => UploadedFile::fake()->create('cedula.pdf', 100, 'application/pdf'),
        ], $overrides);
    }
}
