<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Referido;
use App\Models\Testigo;
use App\Models\Asignacion;
use App\Models\Eleccion;
use App\Models\EleccionMesa;
use App\Models\EleccionPuesto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Traits\EleccionScope;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReferidosController extends Controller
{
    use EleccionScope;

    public function index()
    {
        $eleccionId = $this->resolveEleccionId((int) request('eleccion_id'));

        $referidos = DB::table('referidos as r')
            ->join('personas as p', 'p.id', '=', 'r.persona_id')
            ->join('eleccion_puestos as ep', 'ep.id', '=', 'r.eleccion_puesto_id')
            ->join('territorio_tokens as tt', 'tt.id', '=', 'r.territorio_token_id')
            ->when($eleccionId, function ($q) use ($eleccionId) {
                $q->where('r.eleccion_id', $eleccionId);
            })
            ->select([
                'r.id',
                'r.estado',
                'r.mesa_num',
                'r.eleccion_id',
                'p.cedula',
                'p.nombre',
                'p.email',
                'ep.municipio',
                'ep.puesto',
                'tt.responsable',
                'tt.comuna',
            ])
            ->orderByDesc('r.id')
            ->get();

        $eleccionOperativa = $eleccionId ? Eleccion::find($eleccionId) : null;

        return view('admin.referidos.index', compact('referidos', 'eleccionId', 'eleccionOperativa'));
    }

    public function asignarForm(Referido $referido)
    {
        $persona = DB::table('personas')->where('id', $referido->persona_id)->first();
        $puestos = EleccionPuesto::query()
            ->where('eleccion_id', $referido->eleccion_id)
            ->orderBy('municipio')
            ->orderBy('puesto')
            ->get(['id', 'municipio', 'puesto', 'mesas_total', 'dd', 'mm', 'zz', 'pp']);

        $puestoIdSeleccionado = (int) old('puesto_id', $referido->eleccion_puesto_id);
        $puesto = $puestos->firstWhere('id', $puestoIdSeleccionado) ?: $puestos->first();
        $mesaSugerida = $referido->mesa_num ? (int) $referido->mesa_num : null;
        $mesaSugeridaEstado = null;
        $mesaSugeridaDetalle = null;
        $mesasDisponibles = collect();
        $mesasOcupadasDetalle = collect();

        if ($puesto) {
            [$mesasDisponibles, $mesasOcupadasDetalle] = $this->getMesasDisponiblesYDetalle($referido, $puesto, $persona);
        }

        if ($mesaSugerida && $puesto) {
            if ($mesaSugerida < 1 || $mesaSugerida > (int) $puesto->mesas_total) {
                $mesaSugeridaEstado = 'fuera_rango';
                $mesaSugeridaDetalle = 'La mesa sugerida esta fuera del rango del puesto.';
            } else {
                $mesaKey = $this->buildMesaKey($referido->eleccion_id, $puesto, $mesaSugerida);
                $eleccionMesa = EleccionMesa::where('mesa_key', $mesaKey)->first();

                if (!$eleccionMesa) {
                    $mesaSugeridaEstado = 'no_divipol';
                    $mesaSugeridaDetalle = 'La mesa sugerida no existe en DIVIPOL para esta eleccion.';
                } else {
                    $ocupacion = $this->findMesaOcupacion(
                        $referido->eleccion_id,
                        $eleccionMesa->id,
                        $referido->persona_id,
                        $persona->cedula ?? null
                    );
                    if ($ocupacion) {
                        $mesaSugeridaEstado = 'ocupada';
                        $mesaSugeridaDetalle = 'La mesa sugerida ya esta ocupada por ' .
                            $this->formatOcupacionPersona($ocupacion) . '.';
                    } else {
                        $mesaSugeridaEstado = 'disponible';
                        $mesaSugeridaDetalle = 'La mesa sugerida esta disponible para asignar.';
                    }
                }
            }
        }

        return view('admin.referidos.asignar', compact(
            'referido',
            'persona',
            'puesto',
            'puestos',
            'mesasDisponibles',
            'mesasOcupadasDetalle',
            'mesaSugerida',
            'mesaSugeridaEstado',
            'mesaSugeridaDetalle'
        ));
    }

    public function mesasDisponibles(Request $request, Referido $referido)
    {
        $data = $request->validate([
            'puesto_id' => 'required|integer|exists:eleccion_puestos,id',
        ]);

        $persona = DB::table('personas')->where('id', $referido->persona_id)->first();
        $puesto = EleccionPuesto::query()
            ->where('id', (int) $data['puesto_id'])
            ->where('eleccion_id', $referido->eleccion_id)
            ->first();

        if (!$puesto) {
            return response()->json(['results' => [], 'max' => 0]);
        }

        [$mesasDisponibles, $mesasOcupadasDetalle] = $this->getMesasDisponiblesYDetalle($referido, $puesto, $persona);

        return response()->json([
            'results' => $mesasDisponibles->map(fn ($m) => ['id' => $m, 'text' => 'Mesa ' . $m])->values(),
            'max' => (int) $puesto->mesas_total,
            'ocupadas' => $mesasOcupadasDetalle->map(fn ($o) => [
                'mesa_num' => $o->mesa_num,
                'nombre' => $o->nombre,
                'cedula' => $o->cedula,
                'asignacion_id' => $o->asignacion_id,
            ])->values(),
        ]);
    }

    public function asignar(Request $request, Referido $referido)
    {
        $data = $request->validate([
            'puesto_id' => 'required|integer|exists:eleccion_puestos,id',
            'mesa_num' => 'required|integer|min:1',
        ]);

        if ($referido->estado !== 'referido') {
            return redirect()->route('admin.referidos.index')
                ->with('error', 'Solo se pueden asignar referidos en estado "referido".');
        }

        $puesto = EleccionPuesto::query()
            ->where('id', (int) $data['puesto_id'])
            ->where('eleccion_id', $referido->eleccion_id)
            ->first();
        if (!$puesto) {
            return back()->withErrors(['puesto_id' => 'Puesto no encontrado para esta eleccion.'])->withInput();
        }

        if ((int) $data['mesa_num'] > (int) $puesto->mesas_total) {
            return back()->withErrors(['mesa_num' => 'Mesa fuera de rango del puesto.']);
        }

        $personaReferido = DB::table('personas')->where('id', $referido->persona_id)->first();

        $mesaKey = $this->buildMesaKey($referido->eleccion_id, $puesto, (int) $data['mesa_num']);
        $eleccionMesa = EleccionMesa::where('mesa_key', $mesaKey)->first();

        if (!$eleccionMesa) {
            return back()->withErrors(['mesa_num' => 'No se encontro la mesa en DIVIPOL para esta eleccion.']);
        }

        $ocupacion = $this->findMesaOcupacion(
            $referido->eleccion_id,
            $eleccionMesa->id,
            $referido->persona_id,
            $personaReferido->cedula ?? null
        );
        if ($ocupacion) {
            return back()->withErrors([
                'mesa_num' => 'La mesa ya esta ocupada por ' . $this->formatOcupacionPersona($ocupacion) . '.',
            ])->withInput();
        }

        DB::beginTransaction();
        try {
            // Revalida ocupacion dentro de la transaccion para evitar colisiones por concurrencia.
            $ocupacionEnTx = $this->findMesaOcupacion(
                $referido->eleccion_id,
                $eleccionMesa->id,
                $referido->persona_id,
                $personaReferido->cedula ?? null
            );
            if ($ocupacionEnTx) {
                throw new \RuntimeException('La mesa seleccionada se ocupo durante el proceso. Intenta con otra mesa.');
            }

            $testigo = Testigo::firstOrCreate([
                'persona_id' => $referido->persona_id,
                'tipo' => 'mesa',
            ], [
                'nivel' => null,
                'activo' => 1,
            ]);

            // Compatibilidad con historicos: inactiva cualquier asignacion activa de esta persona
            // en la eleccion (aunque haya quedado con otro testigo legado).
            $asignacionesActivasPersona = DB::table('asignaciones as a')
                ->join('testigos as t', 't.id', '=', 'a.testigo_id')
                ->where('t.persona_id', $referido->persona_id)
                ->where('a.eleccion_id', $referido->eleccion_id)
                ->where('a.estado', 'activo')
                ->where('a.rol', 'testigo_mesa')
                ->select('a.id')
                ->lockForUpdate()
                ->get()
                ->pluck('id')
                ->all();

            if (!empty($asignacionesActivasPersona)) {
                Asignacion::query()
                    ->whereIn('id', $asignacionesActivasPersona)
                    ->update([
                        'estado' => 'inactivo',
                        'updated_at' => now(),
                    ]);
            }

            Asignacion::updateOrCreate([
                'testigo_id' => $testigo->id,
                'eleccion_id' => $referido->eleccion_id,
                'eleccion_mesa_id' => $eleccionMesa->id,
            ], [
                'rol' => 'testigo_mesa',
                'estado' => 'activo',
            ]);

            $puestoOriginal = $referido->eleccion_puesto_id;
            $referido->mesa_num = (int) $data['mesa_num'];
            $referido->eleccion_puesto_id = (int) $puesto->id;
            $referido->estado = 'asignado';
            $this->appendReferidoAudit(
                $referido,
                'Asignado a puesto #' . (int) $puesto->id . ' y mesa ' . (int) $data['mesa_num'] .
                ($puestoOriginal != $puesto->id ? ' (cambio de puesto).' : '.')
            );
            $referido->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['mesa_num' => 'Error al asignar: ' . $e->getMessage()]);
        }

        return redirect()->route('admin.referidos.index')
            ->with('success', 'Referido asignado correctamente.');
    }

    public function asignarPostulado(Referido $referido)
    {
        if ($referido->estado !== 'referido') {
            return redirect()->route('admin.referidos.index')
                ->with('error', 'Solo se pueden asignar referidos en estado "referido".');
        }

        if (empty($referido->eleccion_puesto_id) || empty($referido->mesa_num)) {
            return redirect()->route('admin.referidos.index')
                ->with('error', 'Este referido no tiene puesto o mesa postulados para asignar desde el listado.');
        }

        $puesto = EleccionPuesto::query()
            ->where('id', (int) $referido->eleccion_puesto_id)
            ->where('eleccion_id', $referido->eleccion_id)
            ->first();
        if (!$puesto) {
            return redirect()->route('admin.referidos.index')
                ->with('error', 'El puesto postulado no es valido para esta eleccion.');
        }

        $personaReferido = DB::table('personas')->where('id', $referido->persona_id)->first();
        $mesaKey = $this->buildMesaKey($referido->eleccion_id, $puesto, (int) $referido->mesa_num);
        $eleccionMesa = EleccionMesa::where('mesa_key', $mesaKey)->first();
        if (!$eleccionMesa) {
            return redirect()->route('admin.referidos.index')
                ->with('error', 'La mesa postulada no existe en DIVIPOL para esta eleccion.');
        }

        $ocupacion = $this->findMesaOcupacion(
            $referido->eleccion_id,
            $eleccionMesa->id,
            $referido->persona_id,
            $personaReferido->cedula ?? null
        );
        if ($ocupacion) {
            return redirect()->route('admin.referidos.index')
                ->with('directo_mesa_ocupada', 'La mesa postulada ya esta ocupada por ' . $this->formatOcupacionPersona($ocupacion) . '.')
                ->with('directo_referido_id', $referido->id);
        }

        $request = new Request([
            'puesto_id' => (int) $referido->eleccion_puesto_id,
            'mesa_num' => (int) $referido->mesa_num,
        ]);

        return $this->asignar($request, $referido);
    }

    public function asignarPostuladosMasivo()
    {
        $referidos = Referido::query()
            ->where('estado', 'referido')
            ->whereNotNull('eleccion_puesto_id')
            ->whereNotNull('mesa_num')
            ->orderBy('id')
            ->get();

        $ok = 0;
        $fail = 0;

        foreach ($referidos as $referido) {
            $this->asignar(new Request([
                'puesto_id' => (int) $referido->eleccion_puesto_id,
                'mesa_num' => (int) $referido->mesa_num,
            ]), $referido);

            $referido->refresh();
            if ($referido->estado === 'asignado') {
                $ok++;
            } else {
                $fail++;
            }
        }

        return redirect()->route('admin.referidos.index')
            ->with('success', "Asignación masiva completada. OK: {$ok}. Fallidos: {$fail}.");
    }

    public function liberar(Referido $referido)
    {
        if ($referido->estado !== 'asignado') {
            return redirect()->route('admin.referidos.index')
                ->with('error', 'Solo se puede liberar un referido en estado "asignado".');
        }

        DB::beginTransaction();
        try {
            $asignacionesActivas = DB::table('asignaciones as a')
                ->join('testigos as t', 't.id', '=', 'a.testigo_id')
                ->leftJoin('eleccion_mesas as em', 'em.id', '=', 'a.eleccion_mesa_id')
                ->where('t.persona_id', $referido->persona_id)
                ->where('a.eleccion_id', $referido->eleccion_id)
                ->where('a.estado', 'activo')
                ->where('a.rol', 'testigo_mesa')
                ->select('a.id', 'em.mesa_num')
                ->lockForUpdate()
                ->get();

            if ($asignacionesActivas->isNotEmpty()) {
                DB::table('asignaciones')
                    ->whereIn('id', $asignacionesActivas->pluck('id')->all())
                    ->update([
                        'estado' => 'inactivo',
                        'updated_at' => now(),
                    ]);
            }

            $mesasLiberadas = $asignacionesActivas
                ->pluck('mesa_num')
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();

            $this->appendReferidoAudit(
                $referido,
                $mesasLiberadas
                    ? 'Liberado de mesa(s): ' . implode(', ', $mesasLiberadas)
                    : 'Liberado de mesa (sin asignacion activa encontrada en historial).'
            );

            $referido->estado = 'referido';
            $referido->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->route('admin.referidos.index')
                ->with('error', 'No se pudo liberar la mesa: ' . $e->getMessage());
        }

        return redirect()->route('admin.referidos.index')
            ->with('success', 'Mesa liberada. El referido vuelve a estado "referido".');
    }

    public function rechazar(Referido $referido)
    {
        if ($referido->estado === 'rechazado') {
            return redirect()->route('admin.referidos.index')
                ->with('success', 'El referido ya estaba rechazado.');
        }

        DB::beginTransaction();
        try {
            if ($referido->estado === 'asignado') {
                $asignacionesActivas = DB::table('asignaciones as a')
                    ->join('testigos as t', 't.id', '=', 'a.testigo_id')
                    ->where('t.persona_id', $referido->persona_id)
                    ->where('a.eleccion_id', $referido->eleccion_id)
                    ->where('a.estado', 'activo')
                    ->where('a.rol', 'testigo_mesa')
                    ->select('a.id')
                    ->lockForUpdate()
                    ->get()
                    ->pluck('id')
                    ->all();

                if (!empty($asignacionesActivas)) {
                    DB::table('asignaciones')
                        ->whereIn('id', $asignacionesActivas)
                        ->update([
                            'estado' => 'inactivo',
                            'updated_at' => now(),
                        ]);
                }
            }

            $estadoAnterior = (string) $referido->estado;
            $referido->estado = 'rechazado';
            $this->appendReferidoAudit($referido, 'Cambio de estado: ' . $estadoAnterior . ' -> rechazado.');
            $referido->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->route('admin.referidos.index')
                ->with('error', 'No se pudo rechazar el referido: ' . $e->getMessage());
        }

        return redirect()->route('admin.referidos.index')
            ->with('success', 'Referido rechazado correctamente.');
    }

    public function reactivar(Referido $referido)
    {
        if ($referido->estado !== 'rechazado') {
            return redirect()->route('admin.referidos.index')
                ->with('error', 'Solo se pueden reactivar referidos rechazados.');
        }

        $referido->estado = 'referido';
        $this->appendReferidoAudit($referido, 'Cambio de estado: rechazado -> referido (reactivado).');
        $referido->save();

        return redirect()->route('admin.referidos.index')
            ->with('success', 'Referido reactivado correctamente.');
    }

    public function exportMeta(Request $request)
    {
        $data = $request->validate([
            'tipo' => 'required|in:asignados,validados,total',
        ]);

        $tipo = (string) $data['tipo'];
        $eleccionId = $this->resolveEleccionId();

        if ($tipo === 'total') {
            $referidosAgg = DB::table('referidos')
                ->where('eleccion_id', $eleccionId)
                ->whereNotNull('mesa_num')
                ->whereIn('estado', ['asignado', 'validado', 'postulado', 'acreditado'])
                ->select('eleccion_puesto_id', 'mesa_num')
                ->selectRaw("COALESCE(MAX(CASE WHEN estado = 'acreditado' THEN id END), MAX(CASE WHEN estado = 'postulado' THEN id END), MAX(CASE WHEN estado = 'validado' THEN id END), MAX(CASE WHEN estado = 'asignado' THEN id END)) as best_referido_id")
                ->groupBy('eleccion_puesto_id', 'mesa_num');

            $coordinadoresAgg = DB::table('abogado_coordinaciones as ac')
                ->where('ac.eleccion_id', $eleccionId)
                ->whereNull('ac.released_at')
                ->whereNotNull('ac.eleccion_mesa_id')
                ->whereIn('ac.validacion_estado', ['asignado', 'validado'])
                ->select('ac.eleccion_mesa_id')
                ->selectRaw('MAX(ac.id) as best_coordinacion_id')
                ->groupBy('ac.eleccion_mesa_id');

            $rows = DB::table('eleccion_mesas as em')
                ->join('eleccion_puestos as ep', 'ep.id', '=', 'em.eleccion_puesto_id')
                ->leftJoinSub($referidosAgg, 'ra', function ($join) {
                    $join->on('ra.eleccion_puesto_id', '=', 'em.eleccion_puesto_id')
                        ->on('ra.mesa_num', '=', 'em.mesa_num');
                })
                ->leftJoin('referidos as r', 'r.id', '=', 'ra.best_referido_id')
                ->leftJoin('personas as p', 'p.id', '=', 'r.persona_id')
                ->leftJoin('territorio_tokens as tt', 'tt.id', '=', 'r.territorio_token_id')
                ->leftJoinSub($coordinadoresAgg, 'ca', function ($join) {
                    $join->on('ca.eleccion_mesa_id', '=', 'em.id');
                })
                ->leftJoin('abogado_coordinaciones as ac', 'ac.id', '=', 'ca.best_coordinacion_id')
                ->leftJoin('abogados as a', 'a.id', '=', 'ac.abogado_id')
                ->where('em.eleccion_id', $eleccionId)
                ->where(function ($q) {
                    $q->whereNotNull('r.id')
                        ->orWhereNotNull('ac.id');
                })
                ->orderBy('ep.dd')
                ->orderBy('ep.mm')
                ->orderBy('ep.zz')
                ->orderBy('ep.pp')
                ->orderBy('em.mesa_num')
                ->get([
                    'ep.dd',
                    'ep.departamento',
                    'ep.mm',
                    'ep.municipio',
                    'ep.zz',
                    'ep.pp',
                    'ep.puesto',
                    'em.mesa_num',
                    DB::raw('COALESCE(p.cedula, a.cc) as cedula'),
                    DB::raw('COALESCE(p.nombre, a.nombre) as nombre'),
                    DB::raw('COALESCE(p.telefono, a.telefono) as telefono'),
                    DB::raw('COALESCE(p.email, a.correo) as email'),
                    DB::raw("COALESCE(tt.responsable, '') as responsable"),
                ]);
        } else {
            $rows = DB::table('referidos as r')
                ->join('personas as p', 'p.id', '=', 'r.persona_id')
                ->join('eleccion_puestos as ep', 'ep.id', '=', 'r.eleccion_puesto_id')
                ->leftJoin('territorio_tokens as tt', 'tt.id', '=', 'r.territorio_token_id')
                ->where('r.estado', $tipo === 'validados' ? 'validado' : 'asignado')
                ->whereNotNull('r.mesa_num')
                ->when($eleccionId, function ($q) use ($eleccionId) {
                    $q->where('r.eleccion_id', $eleccionId);
                })
                ->orderBy('ep.dd')
                ->orderBy('ep.mm')
                ->orderBy('ep.zz')
                ->orderBy('ep.pp')
                ->orderBy('r.mesa_num')
                ->get([
                    'ep.dd',
                    'ep.departamento',
                    'ep.mm',
                    'ep.municipio',
                    'ep.zz',
                    'ep.pp',
                    'ep.puesto',
                    'r.mesa_num',
                    'p.cedula',
                    'p.nombre',
                    'p.telefono',
                    'p.email',
                    'tt.responsable',
                ]);
        }

        $payload = [];
        foreach ($rows as $r) {
            [$n1, $n2, $a1, $a2] = $this->splitNombreMeta((string) $r->nombre);
            $payload[] = [
                'Cod Depto' => $this->metaCode($r->dd, 2),
                'Nom Departamento' => $this->normalizeMetaText((string) ($r->departamento ?? '')),
                'Cod Mpio' => $this->metaCode($r->mm, 3),
                'Nom Municipio' => $this->normalizeMetaText((string) ($r->municipio ?? '')),
                'Cod Zona' => $this->metaCode($r->zz),
                'Cod Puesto' => $this->metaCode($r->pp, 2),
                'Nombre Puesto' => $this->normalizeMetaText((string) ($r->puesto ?? '')),
                'Mesa' => (int) $r->mesa_num,
                'Cedula' => (string) ($r->cedula ?? ''),
                'Nombre 1' => $this->normalizeMetaText($n1),
                'Nombre 2' => $this->normalizeMetaText($n2),
                'Apellido 1' => $this->normalizeMetaText($a1),
                'Apellido 2' => $this->normalizeMetaText($a2),
                'Celular' => (string) ($r->telefono ?? ''),
                'Correo' => mb_strtolower($this->normalizeMetaText((string) ($r->email ?? '')), 'UTF-8'),
                'Responsable' => $this->normalizeMetaText((string) ($r->responsable ?? '')),
                'Nivel Educativo' => '',
            ];
        }

        $tipoLabel = $tipo === 'validados' ? 'validados' : ($tipo === 'total' ? 'total' : 'asignados');
        $fileName = 'testigos_meta_' . $tipoLabel . '_' . now()->format('Ymd_His') . '.xlsx';

        $headings = [
            'Cod Depto',
            'Nom Departamento',
            'Cod Mpio',
            'Nom Municipio',
            'Cod Zona',
            'Cod Puesto',
            'Nombre Puesto',
            'Mesa',
            'Cedula',
            'Nombre 1',
            'Nombre 2',
            'Apellido 1',
            'Apellido 2',
            'Celular',
            'Correo',
            'Responsable',
            'Nivel Educativo',
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headings, null, 'A1');
        if (!empty($payload)) {
            $sheet->fromArray($payload, null, 'A2');
        }

        foreach (['A', 'C', 'E', 'F', 'I', 'N'] as $column) {
            $sheet->getStyle($column)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function buildMesaKey(int $eleccionId, EleccionPuesto $puesto, int $mesaNum): string
    {
        return $eleccionId . '-' . $puesto->dd . $puesto->mm . $puesto->zz . $puesto->pp . '-' .
            str_pad((string) $mesaNum, 3, '0', STR_PAD_LEFT);
    }

    private function findMesaOcupacion(
        int $eleccionId,
        int $eleccionMesaId,
        ?int $personaIdIgnorar = null,
        ?string $cedulaIgnorar = null
    ): ?object
    {
        $query = DB::table('asignaciones as a')
            ->join('testigos as t', 't.id', '=', 'a.testigo_id')
            ->join('personas as p', 'p.id', '=', 't.persona_id')
            ->where('a.eleccion_id', $eleccionId)
            ->where('a.eleccion_mesa_id', $eleccionMesaId)
            ->where('a.estado', 'activo')
            ->where('a.rol', 'testigo_mesa')
            ->whereNotNull('p.cedula')
            ->select('a.id', 'a.estado', 'a.rol', 'p.nombre', 'p.cedula', 'p.id as persona_id');

        if (!empty($personaIdIgnorar)) {
            $query->where('p.id', '<>', $personaIdIgnorar);
        }
        if (!empty($cedulaIgnorar)) {
            $query->where(function ($q) use ($cedulaIgnorar) {
                $q->whereNull('p.cedula')
                    ->orWhere('p.cedula', '<>', $cedulaIgnorar);
            });
        }

        return $query->first();
    }

    private function formatOcupacionPersona(object $ocupacion): string
    {
        $nombre = trim((string) ($ocupacion->nombre ?? ''));
        $cedula = trim((string) ($ocupacion->cedula ?? ''));
        if ($nombre !== '' || $cedula !== '') {
            return ($nombre !== '' ? $nombre : 'N/D') . ' (' . ($cedula !== '' ? $cedula : 'N/D') . ')';
        }

        return 'registro de asignacion #' . ($ocupacion->id ?? 'N/D');
    }

    private function appendReferidoAudit(Referido $referido, string $mensaje): void
    {
        $usuario = Auth::user();
        $actor = $usuario ? ($usuario->name ?? $usuario->email ?? ('user#' . $usuario->id)) : 'sistema';
        $linea = '[' . now()->format('Y-m-d H:i:s') . '] ' . $actor . ': ' . $mensaje;

        $referido->observaciones = trim((string) ($referido->observaciones ?? ''));
        $referido->observaciones = $referido->observaciones
            ? ($referido->observaciones . PHP_EOL . $linea)
            : $linea;
    }

    private function getMesasDisponiblesYDetalle(Referido $referido, EleccionPuesto $puesto, ?object $persona): array
    {
        $mesasDelPuesto = EleccionMesa::query()
            ->where('eleccion_id', $referido->eleccion_id)
            ->where('eleccion_puesto_id', $puesto->id)
            ->orderBy('mesa_num')
            ->get(['id', 'mesa_num']);

        $mesasOcupadasDetalle = DB::table('asignaciones as a')
            ->join('testigos as t', 't.id', '=', 'a.testigo_id')
            ->join('personas as p', 'p.id', '=', 't.persona_id')
            ->leftJoin('eleccion_mesas as em', 'em.id', '=', 'a.eleccion_mesa_id')
            ->where('a.eleccion_id', $referido->eleccion_id)
            ->where('a.estado', 'activo')
            ->where('a.rol', 'testigo_mesa')
            ->whereNotNull('p.cedula')
            ->whereIn('a.eleccion_mesa_id', $mesasDelPuesto->pluck('id')->all())
            ->where('p.id', '<>', $referido->persona_id)
            ->when(!empty($persona->cedula), function ($q) use ($persona) {
                $q->where(function ($sub) use ($persona) {
                    $sub->whereNull('p.cedula')
                        ->orWhere('p.cedula', '<>', $persona->cedula);
                });
            })
            ->orderBy('em.mesa_num')
            ->get([
                'a.id as asignacion_id',
                'a.eleccion_mesa_id',
                'em.mesa_num',
                'p.nombre',
                'p.cedula',
            ]);

        $ocupadas = $mesasOcupadasDetalle->pluck('eleccion_mesa_id')->all();
        $mesasDisponibles = $mesasDelPuesto
            ->reject(fn ($m) => in_array($m->id, $ocupadas, true))
            ->pluck('mesa_num')
            ->values();

        return [$mesasDisponibles, $mesasOcupadasDetalle];
    }

    private function splitNombreMeta(string $nombre): array
    {
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre));
        if ($nombre === '') {
            return ['', '', '', ''];
        }
        $parts = explode(' ', $nombre);
        $count = count($parts);

        if ($count === 1) {
            return [$parts[0], '', '', ''];
        }
        if ($count === 2) {
            return [$parts[0], '', $parts[1], ''];
        }
        if ($count === 3) {
            return [$parts[0], $parts[1], $parts[2], ''];
        }

        return [
            $parts[0] ?? '',
            $parts[1] ?? '',
            $parts[2] ?? '',
            implode(' ', array_slice($parts, 3)),
        ];
    }

    private function metaCode($value, ?int $length = null): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '';
        }

        if ($length !== null) {
            return str_pad($value, $length, '0', STR_PAD_LEFT);
        }

        return $value;
    }

    private function normalizeMetaText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $map = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O', 'Ü' => 'U',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'Â' => 'A', 'Ê' => 'E', 'Î' => 'I', 'Ô' => 'O', 'Û' => 'U',
            'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
            'Ñ' => 'N', 'ñ' => 'n',
        ];

        return strtr($value, $map);
    }
}
