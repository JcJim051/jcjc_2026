<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateSellersToAsignaciones extends Command
{
    protected $signature = 'sellers:migrate-to-new {eleccion_id} {--dry-run}';
    protected $description = 'Migra datos basicos de sellers a personas/testigos/asignaciones usando eleccion_mesas.';

    public function handle()
    {
        $eleccionId = (int) $this->argument('eleccion_id');
        $dryRun = (bool) $this->option('dry-run');

        $eleccionExists = DB::table('elecciones')->where('id', $eleccionId)->exists();
        if (!$eleccionExists) {
            $this->error('No existe la eleccion con id: ' . $eleccionId);
            return 1;
        }

        $this->info('Iniciando migracion de sellers. Eleccion: ' . $eleccionId);
        if ($dryRun) {
            $this->info('Modo dry-run activo: no se escribiran cambios.');
        }

        $processed = 0;
        $skipped = 0;
        $missingMesa = 0;
        $createdPersonas = 0;
        $createdTestigos = 0;
        $createdAsignaciones = 0;

        DB::table('sellers')->orderBy('id')->chunk(1000, function ($rows) use (
            $eleccionId,
            $dryRun,
            &$processed,
            &$skipped,
            &$missingMesa,
            &$createdPersonas,
            &$createdTestigos,
            &$createdAsignaciones
        ) {
            foreach ($rows as $row) {
                $processed++;

                // Saltar remanentes o filas sin mesa valida.
                $mesaStr = trim((string) $row->mesa);
                if ($mesaStr === '' || strcasecmp($mesaStr, 'Rem') === 0) {
                    $skipped++;
                    continue;
                }

                $mesaNum = (int) preg_replace('/\D/', '', $mesaStr);
                if ($mesaNum <= 0) {
                    $skipped++;
                    continue;
                }

                $dd = str_pad(preg_replace('/\D/', '', (string) $row->coddep), 2, '0', STR_PAD_LEFT);
                $mm = str_pad(preg_replace('/\D/', '', (string) $row->codmun), 3, '0', STR_PAD_LEFT);
                $zz = str_pad(preg_replace('/\D/', '', (string) $row->codzon), 2, '0', STR_PAD_LEFT);
                $pp = str_pad(preg_replace('/\D/', '', (string) $row->codpuesto), 2, '0', STR_PAD_LEFT);

                if ($dd === '00' || $mm === '000' || $zz === '00' || $pp === '00') {
                    $skipped++;
                    continue;
                }

                $mesaKey = $eleccionId . '-' . $dd . $mm . $zz . $pp . '-' . str_pad((string) $mesaNum, 3, '0', STR_PAD_LEFT);

                $eleccionMesaId = DB::table('eleccion_mesas')
                    ->where('mesa_key', $mesaKey)
                    ->value('id');

                if (!$eleccionMesaId) {
                    $missingMesa++;
                    continue;
                }

                $cedula = trim((string) $row->cedula);
                $email = trim((string) $row->email);
                $telefono = trim((string) $row->telefono);
                $nombre = trim((string) $row->nombre);

                $personaId = null;
                if ($cedula !== '') {
                    $personaId = DB::table('personas')->where('cedula', $cedula)->value('id');
                }
                if (!$personaId && $email !== '') {
                    $personaId = DB::table('personas')->where('email', $email)->value('id');
                }

                if (!$personaId) {
                    if ($dryRun) {
                        $createdPersonas++;
                        $personaId = -1; // placeholder
                    } else {
                        $personaId = DB::table('personas')->insertGetId([
                            'cedula' => $cedula !== '' ? $cedula : null,
                            'nombre' => $nombre !== '' ? $nombre : null,
                            'email' => $email !== '' ? $email : null,
                            'telefono' => $telefono !== '' ? $telefono : null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $createdPersonas++;
                    }
                }

                if ($dryRun && $personaId === -1) {
                    // no testigo/asignacion real en dry-run
                    $createdTestigos++;
                    $createdAsignaciones++;
                    continue;
                }

                $testigoId = DB::table('testigos')
                    ->where('persona_id', $personaId)
                    ->where('tipo', 'mesa')
                    ->value('id');

                if (!$testigoId) {
                    if ($dryRun) {
                        $createdTestigos++;
                        continue;
                    }
                    $testigoId = DB::table('testigos')->insertGetId([
                        'persona_id' => $personaId,
                        'tipo' => 'mesa',
                        'nivel' => null,
                        'activo' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $createdTestigos++;
                }

                if ($dryRun) {
                    $createdAsignaciones++;
                    continue;
                }

                $inserted = DB::table('asignaciones')->insertOrIgnore([
                    'testigo_id' => $testigoId,
                    'eleccion_id' => $eleccionId,
                    'eleccion_mesa_id' => $eleccionMesaId,
                    'rol' => 'testigo_mesa',
                    'estado' => 'activo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($inserted) {
                    $createdAsignaciones++;
                }
            }
        });

        $this->info('Procesados: ' . $processed);
        $this->info('Saltados: ' . $skipped);
        $this->info('Mesas no encontradas: ' . $missingMesa);
        $this->info('Personas creadas: ' . $createdPersonas);
        $this->info('Testigos creados: ' . $createdTestigos);
        $this->info('Asignaciones creadas: ' . $createdAsignaciones);

        return 0;
    }
}
