<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('reuniones')) {
            Schema::create('reuniones', function (Blueprint $table) {
                $table->id();
                $table->date('fecha');
                $table->time('hora_inicio')->nullable();
                $table->time('hora_fin')->nullable();
                $table->string('lugar')->nullable();
                $table->string('aforo')->nullable();
                $table->string('motivo')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('asistencia_sessions')) {
            Schema::create('asistencia_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('reunion_id')->unique();
                $table->string('public_token', 80)->nullable()->unique();
                $table->boolean('activa')->default(true);
                $table->timestamp('abierta_en')->nullable();
                $table->timestamp('cerrada_en')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('controls')) {
            Schema::create('controls', function (Blueprint $table) {
                $table->id();
                $table->string('codigo_abogado', 50);
                $table->unsignedBigInteger('asistencia_session_id')->nullable();
                $table->dateTime('fecha')->nullable();
                $table->string('lugar')->nullable();
                $table->string('motivo')->nullable();
                $table->timestamps();
                $table->unique(['asistencia_session_id', 'codigo_abogado'], 'controls_session_abogado_unique');
            });
        }

        if (!Schema::hasTable('abogado_reunion')) {
            Schema::create('abogado_reunion', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('abogado_id');
                $table->unsignedBigInteger('reunion_id');
                $table->timestamps();
                $table->unique(['abogado_id', 'reunion_id']);
            });
        }

        $legacyDatabase = env('LEGACY_ABOGADOS_DATABASE', 'abogados');
        if (!$this->legacyTableExists($legacyDatabase, 'reuniones') || DB::table('reuniones')->exists()) {
            return;
        }

        $this->importLegacyData($legacyDatabase);
    }

    public function down()
    {
        // Reparación no destructiva: nunca elimina reuniones ni asistencias importadas.
    }

    private function legacyTableExists(string $database, string $table): bool
    {
        return (int) DB::table('information_schema.tables')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->count() > 0;
    }

    private function importLegacyData(string $legacyDatabase): void
    {
        $database = str_replace('`', '``', $legacyDatabase);
        $now = now();

        foreach (DB::table($database.'.reuniones')->orderBy('id')->get() as $legacy) {
            DB::table('reuniones')->insertOrIgnore([
                'id' => $legacy->id,
                'fecha' => substr((string) $legacy->fecha, 0, 10),
                'hora_inicio' => $legacy->hora_inicio,
                'hora_fin' => $legacy->hora_fin,
                'lugar' => $legacy->lugar,
                'aforo' => $legacy->aforo,
                'motivo' => 'Reunión histórica',
                'created_at' => $legacy->created_at ?: $now,
                'updated_at' => $legacy->updated_at ?: $now,
            ]);
        }

        if ($this->legacyTableExists($legacyDatabase, 'asistencia_sessions')) {
            foreach (DB::table($database.'.asistencia_sessions')->orderBy('id')->get() as $legacy) {
                DB::table('asistencia_sessions')->insertOrIgnore([
                    'id' => $legacy->id,
                    'reunion_id' => $legacy->reunion_id,
                    'public_token' => $legacy->public_token ?: null,
                    'activa' => $legacy->activa,
                    'abierta_en' => $legacy->abierta_en,
                    'cerrada_en' => $legacy->cerrada_en,
                    'created_at' => $legacy->created_at ?: $now,
                    'updated_at' => $legacy->updated_at ?: $now,
                ]);
            }
        }

        if (!$this->legacyTableExists($legacyDatabase, 'controls') ||
            !$this->legacyTableExists($legacyDatabase, 'abogados')) {
            return;
        }

        $legacyControls = DB::table($database.'.controls as c')
            ->join($database.'.abogados as a', DB::raw('CAST(a.id AS CHAR)'), '=', 'c.codigo_abogado')
            ->whereNotNull('c.asistencia_session_id')
            ->select('c.*', 'a.cc')
            ->orderBy('c.id')
            ->get();

        foreach ($legacyControls as $legacy) {
            $abogadoId = $this->mainAbogadoIdByCedula($legacy->cc);
            if (!$abogadoId || !DB::table('asistencia_sessions')->where('id', $legacy->asistencia_session_id)->exists()) {
                continue;
            }

            DB::table('controls')->insertOrIgnore([
                'id' => $legacy->id,
                'codigo_abogado' => (string) $abogadoId,
                'asistencia_session_id' => $legacy->asistencia_session_id,
                'fecha' => $legacy->fecha,
                'lugar' => $legacy->lugar,
                'motivo' => $legacy->motivo,
                'created_at' => $legacy->created_at ?: $now,
                'updated_at' => $legacy->updated_at ?: $now,
            ]);

            $reunionId = DB::table('asistencia_sessions')
                ->where('id', $legacy->asistencia_session_id)
                ->value('reunion_id');
            if ($reunionId) {
                DB::table('abogado_reunion')->insertOrIgnore([
                    'abogado_id' => $abogadoId,
                    'reunion_id' => $reunionId,
                    'created_at' => $legacy->created_at ?: $now,
                    'updated_at' => $legacy->updated_at ?: $now,
                ]);
            }
        }
    }

    private function mainAbogadoIdByCedula(?string $cedula): ?int
    {
        $normalized = preg_replace('/\D+/', '', (string) $cedula) ?: '';
        if ($normalized === '') {
            return null;
        }

        $ids = DB::table('abogados')
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(cc, '.', ''), ',', ''), '-', ''), ' ', '') = ?",
                [$normalized]
            )
            ->limit(2)
            ->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }
};
