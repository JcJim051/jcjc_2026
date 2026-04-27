<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCandidatosFromSellers extends Command
{
    protected $signature = 'candidatos:import-from-sellers {eleccion_id} {--dry-run}';
    protected $description = 'Crea candidatos y asigna mesas a candidatos usando la tabla sellers.';

    public function handle()
    {
        $eleccionId = (int) $this->argument('eleccion_id');
        $dryRun = (bool) $this->option('dry-run');

        if (!DB::table('elecciones')->where('id', $eleccionId)->exists()) {
            $this->error('No existe la eleccion con id: ' . $eleccionId);
            return 1;
        }

        $this->info('Importando candidatos desde sellers. Eleccion: ' . $eleccionId);
        if ($dryRun) {
            $this->info('Modo dry-run activo: no se escribiran cambios.');
        }

        $createdCandidatos = 0;
        $createdAsignaciones = 0;
        $missingMesas = 0;

        DB::table('sellers')
            ->whereNotNull('candidato')
            ->where('mesa', '<>', 'Rem')
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (
                $eleccionId,
                $dryRun,
                &$createdCandidatos,
                &$createdAsignaciones,
                &$missingMesas
            ) {
                foreach ($rows as $row) {
                    $mesaStr = trim((string) $row->mesa);
                    $mesaNum = (int) preg_replace('/\D/', '', $mesaStr);
                    if ($mesaNum <= 0) {
                        continue;
                    }

                    $dd = str_pad(preg_replace('/\D/', '', (string) $row->coddep), 2, '0', STR_PAD_LEFT);
                    $mm = str_pad(preg_replace('/\D/', '', (string) $row->codmun), 3, '0', STR_PAD_LEFT);
                    $zz = str_pad(preg_replace('/\D/', '', (string) $row->codzon), 2, '0', STR_PAD_LEFT);
                    $pp = str_pad(preg_replace('/\D/', '', (string) $row->codpuesto), 2, '0', STR_PAD_LEFT);

                    if ($dd === '00' || $mm === '000' || $zz === '00' || $pp === '00') {
                        continue;
                    }

                    $mesaKey = $eleccionId . '-' . $dd . $mm . $zz . $pp . '-' . str_pad((string) $mesaNum, 3, '0', STR_PAD_LEFT);

                    $eleccionMesaId = DB::table('eleccion_mesas')
                        ->where('mesa_key', $mesaKey)
                        ->value('id');

                    if (!$eleccionMesaId) {
                        $missingMesas++;
                        continue;
                    }

                    $codigo = (int) $row->candidato;
                    if ($codigo <= 0) {
                        continue;
                    }

                    $candidatoId = DB::table('candidatos')
                        ->where('eleccion_id', $eleccionId)
                        ->where('codigo', $codigo)
                        ->value('id');

                    if (!$candidatoId) {
                        if ($dryRun) {
                            $createdCandidatos++;
                            $candidatoId = -1;
                        } else {
                            $candidatoId = DB::table('candidatos')->insertGetId([
                                'eleccion_id' => $eleccionId,
                                'codigo' => $codigo,
                                'nombre' => 'Candidato ' . $codigo,
                                'activo' => 1,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $createdCandidatos++;
                        }
                    }

                    if ($dryRun && $candidatoId === -1) {
                        $createdAsignaciones++;
                        continue;
                    }

                    $inserted = DB::table('eleccion_mesa_candidatos')->insertOrIgnore([
                        'eleccion_id' => $eleccionId,
                        'eleccion_mesa_id' => $eleccionMesaId,
                        'candidato_id' => $candidatoId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    if ($inserted) {
                        $createdAsignaciones++;
                    }
                }
            });

        $this->info('Candidatos creados: ' . $createdCandidatos);
        $this->info('Asignaciones creadas: ' . $createdAsignaciones);
        $this->info('Mesas no encontradas: ' . $missingMesas);

        return 0;
    }
}

