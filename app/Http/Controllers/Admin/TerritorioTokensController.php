<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Eleccion;
use App\Models\EleccionPuesto;
use App\Models\Referido;
use App\Models\TerritorioToken;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Traits\EleccionScope;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TerritorioTokensController extends Controller
{
    use EleccionScope;

    public function index(Request $request)
    {
        [$elecciones, $tokens, $eleccionId] = $this->buildTokensDataset((int) $request->get('eleccion_id'));

        return view('admin.territorio_tokens.index', compact('elecciones', 'tokens', 'eleccionId'));
    }

    public function export(Request $request)
    {
        [, $tokens] = $this->buildTokensDataset((int) $request->get('eleccion_id'));

        $fileName = 'tokens_territoriales_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($tokens) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Tokens');

            $headers = [
                'ID',
                'Tipo',
                'Encargado',
                'Eleccion ID',
                'Eleccion',
                'Departamento',
                'Municipio',
                'Comunas',
                'Mesas',
                'Meta %',
                'Meta objetivo',
                'Meta pactada',
                'Avance pactada %',
                'Ocupados',
                'Referidos token',
                'Referidos municipio',
                'Faltan',
                'Faltan pactada',
                'Token',
                'Links',
                'Activo',
                'Expira',
                'Creado',
            ];
            $sheet->fromArray($headers, null, 'A1');

            $row = 2;
            foreach ($tokens as $t) {
                $links = [];
                if (($t->modulo_resuelto ?? null) === 'reporte_operativo') {
                    $links[] = 'Reporte: ' . route('public.coordinador_reportes.identify', $t->token);
                } elseif (($t->modulo_resuelto ?? null) === 'comision_alertas') {
                    $links[] = 'Comision: ' . route('public.comision_alertas.index', $t->token);
                } else {
                    if (!$t->es_consulta) {
                        $links[] = 'Formulario: ' . route('public.referidos.form', $t->token);
                    }
                    $links[] = 'Seguimiento: ' . route('public.referidos.seguimiento', $t->token);
                }

                $sheet->fromArray([
                    $t->id,
                    ($t->modulo_resuelto === 'reporte_operativo'
                        ? 'Reporte Operativo'
                        : (($t->modulo_resuelto === 'comision_alertas') ? 'Comision' : ($t->es_consulta ? 'Consulta' : 'Referidos'))),
                    $t->responsable ?: 'N/D',
                    $t->eleccion_id,
                    $t->eleccion_nombre ?? '',
                    $t->departamento_nombre ?? '',
                    ($t->municipio_nombre ?? '') . (($t->modulo_resuelto === 'comision_alertas' && !empty($t->zz_label)) ? ' | ' . $t->zz_label : ''),
                    $t->comuna ?? '',
                    (int) ($t->mesas_total ?? 0),
                    (int) ($t->meta_testigos_pct ?? 100),
                    (int) ($t->meta_objetivo ?? 0),
                    (int) ($t->meta_pactada ?? 0),
                    $t->avance_pactada_pct ?? '',
                    (int) ($t->ocupados_total ?? 0),
                    (int) ($t->referidos_token_total ?? 0),
                    (int) ($t->referidos_municipio_total ?? 0),
                    (int) ($t->faltan_total ?? 0),
                    (int) ($t->faltan_pactada ?? 0),
                    $t->token,
                    implode(' | ', $links),
                    $t->activo ? 'SI' : 'NO',
                    optional($t->expires_at)->format('Y-m-d H:i:s'),
                    optional($t->created_at)->format('Y-m-d H:i:s'),
                ], null, 'A' . $row);
                $row++;
            }

            foreach (range('A', 'W') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function buildTokensDataset(?int $requestedEleccionId = null): array
    {
        $eleccionId = $this->resolveEleccionId($requestedEleccionId);
        $elecciones = Eleccion::orderByDesc('id')->get();
        $tokens = TerritorioToken::query()
            ->when($eleccionId, fn ($q) => $q->where('eleccion_id', $eleccionId))
            ->orderByDesc('id')
            ->get();
        $eleccionesMap = $elecciones->keyBy('id');

        $municipiosMap = EleccionPuesto::query()
            ->whereIn('eleccion_id', $tokens->pluck('eleccion_id')->unique()->values())
            ->select('eleccion_id', 'dd', 'mm', 'departamento', 'municipio')
            ->distinct()
            ->get()
            ->keyBy(function ($r) {
                return $r->eleccion_id . '|' . $r->dd . '|' . $r->mm;
            });

        $referidosPorToken = Referido::query()
            ->selectRaw('territorio_token_id, COUNT(*) as total')
            ->groupBy('territorio_token_id')
            ->pluck('total', 'territorio_token_id');

        $referidosPorMunicipio = Referido::query()
            ->join('territorio_tokens as tt', 'tt.id', '=', 'referidos.territorio_token_id')
            ->selectRaw('tt.eleccion_id, tt.dd, tt.mm, COUNT(*) as total')
            ->groupBy('tt.eleccion_id', 'tt.dd', 'tt.mm')
            ->get()
            ->keyBy(function ($r) {
                return $r->eleccion_id . '|' . $r->dd . '|' . $r->mm;
            });

        $tokens = $tokens->map(function ($t) use ($municipiosMap, $eleccionesMap, $referidosPorToken, $referidosPorMunicipio) {
            $geoCodes = $this->normalizeMunicipioCodes($t->municipios, $t->dd, $t->mm);
            $geoRows = collect($geoCodes)->map(function ($geoCode) use ($municipiosMap, $t) {
                [$dd, $mm] = explode('-', $geoCode);
                return $municipiosMap->get($t->eleccion_id . '|' . $dd . '|' . $mm);
            })->filter();

            $firstGeo = $geoRows->first();
            $t->departamento_nombre = $firstGeo->departamento ?? null;
            $t->municipio_nombre = $firstGeo->municipio ?? null;
            $t->municipios_label = $this->formatMunicipiosLabel($geoRows);
            $t->eleccion_nombre = optional($eleccionesMap->get($t->eleccion_id))->nombre;
            $metaPct = (int) (optional($eleccionesMap->get($t->eleccion_id))->meta_testigos_pct ?? 100);
            $metaPct = max(0, min(100, $metaPct));
            $t->meta_testigos_pct = $metaPct;
            $zonasToken = $this->parseZonasToken($t->zz);
            $t->zz_label = $this->formatZonasLabel($zonasToken);

            $mesasQuery = DB::table('eleccion_mesas as em')
                ->join('eleccion_puestos as ep', 'ep.id', '=', 'em.eleccion_puesto_id')
                ->where('em.eleccion_id', $t->eleccion_id);
            $this->applyGeoCodesScope($mesasQuery, $geoCodes, 'ep.dd', 'ep.mm');
            if (($t->modulo_resuelto ?? null) === 'comision_alertas' && !empty($zonasToken)) {
                $mesasQuery->whereIn('ep.zz', $zonasToken);
            }
            $comunasToken = $this->parseComunasToken($t->comuna);
            if (!empty($comunasToken)) {
                $mesasQuery->whereIn('ep.comuna', $comunasToken);
            }
            $t->mesas_total = (int) $mesasQuery->count();

            $puestos = DB::table('eleccion_puestos as ep')
                ->where('ep.eleccion_id', $t->eleccion_id)
                ->when(!empty($comunasToken), function ($q) use ($comunasToken) {
                    $q->whereIn('ep.comuna', $comunasToken);
                });
            $this->applyGeoCodesScope($puestos, $geoCodes, 'ep.dd', 'ep.mm');
            if (($t->modulo_resuelto ?? null) === 'comision_alertas' && !empty($zonasToken)) {
                $puestos->whereIn('ep.zz', $zonasToken);
            }
            $puestos = $puestos
                ->pluck('ep.id');

            $metaObjetivo = 0;
            if ($puestos->isNotEmpty()) {
                $mesasByPuesto = DB::table('eleccion_mesas')
                    ->selectRaw('eleccion_puesto_id, COUNT(*) as total_mesas')
                    ->where('eleccion_id', $t->eleccion_id)
                    ->whereIn('eleccion_puesto_id', $puestos->all())
                    ->groupBy('eleccion_puesto_id')
                    ->get();

                foreach ($mesasByPuesto as $row) {
                    $metaObjetivo += (int) floor(((int) $row->total_mesas) * ($metaPct / 100));
                }
            }
            $t->meta_objetivo = $metaObjetivo;
            $metaPactada = 0;
            if ($puestos->isNotEmpty()) {
                $metaPactada = (int) DB::table('eleccion_puesto_metas')
                    ->where('eleccion_id', $t->eleccion_id)
                    ->whereIn('eleccion_puesto_id', $puestos->all())
                    ->sum('meta_pactada');
            }
            $t->meta_pactada = $metaPactada;

            if (($t->modulo_resuelto ?? null) === 'comision_alertas') {
                $t->referidos_token_total = null;
                $t->referidos_municipio_total = null;
                $t->referidos_token_municipio = 'N/D';
                $t->ocupados_total = null;
                $t->referidos_total = null;
                $t->faltan_total = null;
                $t->faltan_pactada = null;
                $t->avance_pactada_pct = null;
                $t->meta_objetivo = null;
                $t->meta_pactada = null;
            } else {
                $t->referidos_token_total = (int) ($referidosPorToken[$t->id] ?? 0);
                $t->referidos_municipio_total = (int) collect($geoCodes)->sum(function ($geoCode) use ($referidosPorMunicipio, $t) {
                    return (int) (($referidosPorMunicipio[$t->eleccion_id . '|' . str_replace('-', '|', $geoCode)]->total ?? 0));
                });
                $t->referidos_token_municipio = $t->referidos_token_total . '/' . $t->referidos_municipio_total;

                $t->ocupados_total = (int) Referido::query()
                    ->where('territorio_token_id', $t->id)
                    ->where('estado', '<>', 'rechazado')
                    ->count();
                $t->referidos_total = $t->ocupados_total;
                $t->faltan_total = max($metaObjetivo - $t->ocupados_total, 0);
                $t->faltan_pactada = max($metaPactada - $t->ocupados_total, 0);
                $t->avance_pactada_pct = $metaPactada > 0
                    ? round(($t->ocupados_total / $metaPactada) * 100, 1)
                    : null;
            }
            return $t;
        });

        return [$elecciones, $tokens, $eleccionId];
    }

    public function municipios(Request $request)
    {
        $eleccionId = (int) $request->get('eleccion_id');
        $search = trim((string) $request->get('q', ''));
        $eleccion = Eleccion::find($eleccionId);

        $query = EleccionPuesto::where('eleccion_id', $eleccionId);

        // Respetar alcance configurado en la eleccion seleccionada.
        if ($eleccion) {
            $alcanceDd = trim((string) ($eleccion->alcance_dd ?? ''));
            $alcanceMm = trim((string) ($eleccion->alcance_mm ?? ''));

            if ($alcanceDd !== '') {
                $query->where('dd', str_pad($alcanceDd, 2, '0', STR_PAD_LEFT));
            }

            if ($alcanceMm !== '') {
                $mmList = collect(explode(',', $alcanceMm))
                    ->map(fn ($mm) => str_pad(trim((string) $mm), 3, '0', STR_PAD_LEFT))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (!empty($mmList)) {
                    $query->whereIn('mm', $mmList);
                }
            }
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('departamento', 'like', '%' . $search . '%')
                  ->orWhere('municipio', 'like', '%' . $search . '%');
            });
        }

        $items = $query
            ->select('dd', 'mm', 'departamento', 'municipio')
            ->distinct()
            ->orderBy('departamento')
            ->orderBy('municipio')
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->dd . '-' . $r->mm,
                    'text' => $r->departamento . ' / ' . $r->municipio,
                    'dd' => $r->dd,
                    'mm' => $r->mm,
                ];
            });

        return response()->json(['results' => $items]);
    }

    public function comunas(Request $request)
    {
        $eleccionId = (int) $request->get('eleccion_id');
        $municipioCodigo = (string) $request->get('municipio_codigo', '');
        $search = trim((string) $request->get('q', ''));

        if ($municipioCodigo === '' || strpos($municipioCodigo, '-') === false) {
            return response()->json(['results' => []]);
        }

        [$dd, $mm] = explode('-', $municipioCodigo);

        $query = EleccionPuesto::where('eleccion_id', $eleccionId)
            ->where('dd', $dd)
            ->where('mm', $mm)
            ->whereNotNull('comuna')
            ->select('comuna')
            ->distinct();

        if ($search !== '') {
            $query->where('comuna', 'like', '%' . $search . '%');
        }

        $items = $query->orderBy('comuna')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->comuna,
                'text' => $r->comuna,
            ]);

        return response()->json(['results' => $items]);
    }

    public function zonas(Request $request)
    {
        $eleccionId = (int) $request->get('eleccion_id');
        $municipioCodigo = trim((string) $request->get('municipio_codigo', ''));

        if ($eleccionId <= 0 || $municipioCodigo === '' || strpos($municipioCodigo, '-') === false) {
            return response()->json(['results' => []]);
        }

        [$dd, $mm] = explode('-', $municipioCodigo);

        $items = EleccionPuesto::query()
            ->where('eleccion_id', $eleccionId)
            ->where('dd', $dd)
            ->where('mm', $mm)
            ->select('zz')
            ->distinct()
            ->orderBy('zz')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->zz,
                'text' => 'Zona ' . $r->zz,
            ]);

        return response()->json(['results' => $items]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'eleccion_id' => 'required|integer|exists:elecciones,id',
            'token_mode' => 'nullable|in:referidos,consulta,reporte_operativo,comision_alertas',
            'municipio_codigo' => 'nullable|string',
            'municipios_multi' => 'nullable|array',
            'municipios_multi.*' => 'string',
            'comuna' => 'nullable',
            'comuna.*' => 'nullable|string|max:100',
            'zona' => 'nullable|array',
            'zona.*' => 'nullable|string|max:2',
            'responsable' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date',
        ]);

        $mode = $data['token_mode'] ?? 'referidos';
        $zonas = $this->normalizeZonasInput($data['zona'] ?? []);
        $municipiosSeleccionados = collect();
        if (!empty($data['municipio_codigo'])) {
            $municipiosSeleccionados->push((string) $data['municipio_codigo']);
        }
        $municipiosSeleccionados = $municipiosSeleccionados
            ->merge($data['municipios_multi'] ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '' && strpos($value, '-') !== false)
            ->unique()
            ->values();

        if ($municipiosSeleccionados->isEmpty()) {
            return back()->withErrors(['municipio_codigo' => 'Debes seleccionar al menos un municipio.'])->withInput();
        }

        if ($mode === 'comision_alertas' && ($municipiosSeleccionados->count() !== 1 || empty($zonas))) {
            return back()->withErrors(['zona' => 'Debes seleccionar exactamente un municipio y al menos una zona para la comisión.'])->withInput();
        }

        $first = (string) $municipiosSeleccionados->first();
        [$dd, $mm] = explode('-', $first);

        if ($mode === 'comision_alertas') {
            $zonasValidas = EleccionPuesto::query()
                ->where('eleccion_id', (int) $data['eleccion_id'])
                ->where('dd', $dd)
                ->where('mm', $mm)
                ->whereIn('zz', $zonas)
                ->distinct()
                ->pluck('zz')
                ->map(fn ($value) => str_pad(trim((string) $value), 2, '0', STR_PAD_LEFT))
                ->all();

            if (count(array_diff($zonas, $zonasValidas)) > 0) {
                return back()->withErrors(['zona' => 'Una o varias zonas seleccionadas no existen en la DIVIPOL de la elección.'])->withInput();
            }
        }

        $token = Str::random(32);

        TerritorioToken::create([
            'eleccion_id' => $data['eleccion_id'],
            'dd' => $dd,
            'mm' => $mm,
            'zz' => $mode === 'comision_alertas' ? implode(',', $zonas) : null,
            'comuna' => ($mode === 'consulta' || $mode === 'comision_alertas' || $municipiosSeleccionados->count() > 1)
                ? null
                : $this->normalizeComunasInput($data['comuna'] ?? null),
            'es_consulta' => $mode === 'consulta',
            'municipios' => $municipiosSeleccionados->all(),
            'modulo' => $mode,
            'token' => $token,
            'responsable' => $data['responsable'] ?? null,
            'activo' => true,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return redirect()->route('admin.territorio_tokens.index')
            ->with('success', 'Token creado correctamente.');
    }

    public function bloquearTodos(Request $request)
    {
        $eleccionId = $this->resolveEleccionId((int) $request->get('eleccion_id'));

        if (!$eleccionId) {
            return redirect()->route('admin.territorio_tokens.index')
                ->with('success', 'No se encontro una eleccion operativa para bloquear tokens.');
        }

        $updated = TerritorioToken::query()
            ->where('eleccion_id', $eleccionId)
            ->where('activo', true)
            ->update(['activo' => false]);

        return redirect()->route('admin.territorio_tokens.index', ['eleccion_id' => $eleccionId])
            ->with('success', 'Tokens bloqueados: ' . $updated);
    }

    public function projection(Request $request, TerritorioToken $token)
    {
        $modulo = $token->modulo ?: ($token->es_consulta ? 'consulta' : 'referidos');
        if ($modulo === 'reporte_operativo') {
            $target = 'reporte';
            $publicUrl = route('public.coordinador_reportes.identify', $token->token);
        } elseif ($modulo === 'comision_alertas') {
            $target = 'comision';
            $publicUrl = route('public.comision_alertas.index', $token->token);
        } else {
            $target = $request->get('target') === 'seguimiento' || $token->es_consulta
                ? 'seguimiento'
                : 'formulario';

            $publicUrl = $target === 'seguimiento'
                ? route('public.referidos.seguimiento', $token->token)
                : route('public.referidos.form', $token->token);
        }

        $svg = (new Writer(
            new ImageRenderer(new RendererStyle(760, 3), new SvgImageBackEnd())
        ))->writeString($publicUrl);
        $qrSvg = trim(substr($svg, strpos($svg, "\n") + 1));

        $eleccion = Eleccion::find($token->eleccion_id);
        $territorio = EleccionPuesto::query()
            ->where('eleccion_id', $token->eleccion_id)
            ->where('dd', $token->dd)
            ->where('mm', $token->mm)
            ->select('departamento', 'municipio')
            ->first();

        $zonasLabel = $this->formatZonasLabel($this->parseZonasToken($token->zz));

        return view('admin.territorio_tokens.projection', compact(
            'token',
            'target',
            'publicUrl',
            'qrSvg',
            'eleccion',
            'territorio',
            'zonasLabel'
        ));
    }

    public function toggle(TerritorioToken $token)
    {
        $token->activo = !$token->activo;
        $token->save();

        return redirect()->route('admin.territorio_tokens.index')
            ->with('success', 'Estado del token actualizado.');
    }

    public function destroy(TerritorioToken $token)
    {
        $token->delete();

        return redirect()->route('admin.territorio_tokens.index')
            ->with('success', 'Token eliminado correctamente.');
    }

    public function update(Request $request, TerritorioToken $token)
    {
        $data = $request->validate([
            'responsable' => 'nullable|string|max:255',
            'comuna' => 'nullable|string|max:255',
            'zz' => 'nullable|array',
            'zz.*' => 'nullable|string|max:2',
            'expires_at' => 'nullable|date',
            'activo' => 'nullable|boolean',
            'municipios_multi' => 'nullable|array',
            'municipios_multi.*' => 'string',
        ]);

        $municipios = collect($data['municipios_multi'] ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '' && strpos($value, '-') !== false)
            ->unique()
            ->values();

        if ($municipios->isEmpty()) {
            $municipios = collect($this->normalizeMunicipioCodes($token->municipios, $token->dd, $token->mm));
        }

        $first = (string) $municipios->first();
        if ($first === '' || strpos($first, '-') === false) {
            return back()->withErrors(['municipios_multi' => 'Debes dejar al menos un municipio válido.'])->withInput();
        }

        [$dd, $mm] = explode('-', $first);
        $token->dd = $dd;
        $token->mm = $mm;
        $token->municipios = $municipios->all();

        if (($token->modulo_resuelto ?? null) === 'comision_alertas') {
            if ($municipios->count() !== 1) {
                return back()->withErrors(['municipios_multi' => 'El token de comisión debe conservar un solo municipio.'])->withInput();
            }

            $zonas = $this->normalizeZonasInput($data['zz'] ?? []);
            if (empty($zonas)) {
                return back()->withErrors(['zz' => 'Debes seleccionar al menos una zona para el token de comisión.'])->withInput();
            }

            $zonasValidas = EleccionPuesto::query()
                ->where('eleccion_id', $token->eleccion_id)
                ->where('dd', $dd)
                ->where('mm', $mm)
                ->whereIn('zz', $zonas)
                ->distinct()
                ->pluck('zz')
                ->map(fn ($value) => str_pad(trim((string) $value), 2, '0', STR_PAD_LEFT))
                ->all();

            if (count(array_diff($zonas, $zonasValidas)) > 0) {
                return back()->withErrors(['zz' => 'Una o varias zonas seleccionadas no existen en la DIVIPOL de la elección.'])->withInput();
            }

            $token->zz = implode(',', $zonas);
            $token->comuna = null;
        } elseif ($token->es_consulta || $municipios->count() > 1) {
            $token->zz = null;
            $token->comuna = null;
        } else {
            $token->zz = null;
            $token->comuna = $this->normalizeComunasInput($data['comuna'] ?? null);
        }

        $token->responsable = trim((string) ($data['responsable'] ?? '')) ?: null;
        if (!$token->modulo) {
            $token->modulo = $token->es_consulta ? 'consulta' : 'referidos';
        }
        $token->expires_at = $data['expires_at'] ?? null;
        $token->activo = (bool) ($data['activo'] ?? $token->activo);
        $token->save();

        return redirect()->route('admin.territorio_tokens.index')
            ->with('success', 'Token actualizado correctamente.');
    }

    private function normalizeComunasInput($value): ?string
    {
        if (is_array($value)) {
            $items = collect($value)->map(fn($v) => trim((string) $v))->filter()->unique()->values()->all();
            return empty($items) ? null : implode(',', $items);
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function parseComunasToken(?string $value): array
    {
        if ($value === null) {
            return [];
        }
        return collect(explode(',', $value))
            ->map(fn($x) => trim((string) $x))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeZonasInput($value): array
    {
        return collect(is_array($value) ? $value : [$value])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->map(fn ($item) => str_pad($item, 2, '0', STR_PAD_LEFT))
            ->unique()
            ->values()
            ->all();
    }

    private function parseZonasToken(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->map(fn ($item) => str_pad($item, 2, '0', STR_PAD_LEFT))
            ->unique()
            ->values()
            ->all();
    }

    private function formatZonasLabel($zonas): ?string
    {
        $items = collect($zonas)->filter()->values();
        if ($items->isEmpty()) {
            return null;
        }

        return 'Zonas ' . $items->implode(', ');
    }

    private function normalizeMunicipioCodes($municipios, ?string $dd, ?string $mm): array
    {
        $items = collect(is_array($municipios) ? $municipios : [])
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '' && strpos($value, '-') !== false)
            ->unique()
            ->values();

        if ($items->isEmpty() && $dd && $mm) {
            $items = collect([trim((string) $dd) . '-' . trim((string) $mm)]);
        }

        return $items->all();
    }

    private function applyGeoCodesScope($query, array $geoCodes, string $ddColumn = 'dd', string $mmColumn = 'mm'): void
    {
        $validGeoCodes = collect($geoCodes)
            ->filter(fn ($value) => strpos((string) $value, '-') !== false)
            ->values()
            ->all();

        $query->where(function ($scope) use ($validGeoCodes, $ddColumn, $mmColumn) {
            foreach ($validGeoCodes as $geoCode) {
                [$dd, $mm] = explode('-', (string) $geoCode);
                $scope->orWhere(function ($geo) use ($ddColumn, $mmColumn, $dd, $mm) {
                    $geo->where($ddColumn, $dd)->where($mmColumn, $mm);
                });
            }
        });
    }

    private function formatMunicipiosLabel($geoRows): string
    {
        $labels = collect($geoRows)
            ->map(fn ($row) => trim(($row->departamento ?? '') . ' / ' . ($row->municipio ?? ''), ' /'))
            ->filter()
            ->values();

        if ($labels->isEmpty()) {
            return 'N/D';
        }

        if ($labels->count() <= 2) {
            return $labels->implode(' | ');
        }

        return $labels->take(2)->implode(' | ') . ' +' . ($labels->count() - 2);
    }
}
