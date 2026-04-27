<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento Referidos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.dataTables.min.css">
    <style>
        :root {
            --brand-primary: #0f4c81;
            --brand-accent: #00a86b;
            --brand-bg-1: #f3f8ff;
            --brand-bg-2: #e8f5f0;
        }
        body {
            background: linear-gradient(130deg, var(--brand-bg-1), var(--brand-bg-2));
            min-height: 100vh;
            color: #17314b;
        }
        .brand-wrap {
            max-width: 1220px;
            margin: 30px auto;
            padding: 0 12px;
        }
        .brand-card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 12px 34px rgba(15, 76, 129, 0.16);
            overflow: hidden;
            background: rgba(255, 255, 255, 0.96);
        }
        .brand-header {
            background: linear-gradient(120deg, var(--brand-primary), #1f6aa8);
            color: #fff;
            padding: 20px 24px 16px;
        }
        .brand-title-row {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
        }
        .brand-logo-wrap {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.28);
            flex: 0 0 auto;
        }
        .brand-logo {
            width: 30px;
            height: 30px;
            object-fit: contain;
            display: block;
        }
        .brand-header h4 {
            margin: 0;
            font-weight: 700;
        }
        .brand-body {
            padding: 20px 24px 24px;
        }
        .brand-link {
            color: var(--brand-primary);
            font-weight: 600;
            text-decoration: none;
        }
        .brand-link:hover {
            color: #0c406c;
            text-decoration: underline;
        }
        .top-action {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 10px;
        }
        .btn-referir {
            background: #0f4c81;
            border-color: #0f4c81;
            color: #fff;
            font-weight: 700;
            border-radius: 10px;
            padding: 8px 14px;
            text-decoration: none;
        }
        .btn-referir:hover {
            color: #fff;
            background: #0c406c;
            border-color: #0c406c;
            text-decoration: none;
        }
        .table-wrap {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e5edf5;
        }
        .summary-grid {
            margin-bottom: 14px;
        }
        .summary-grid h5 {
            margin: 0 0 10px;
            color: #123a5f;
            font-weight: 700;
        }
        .summary-table th,
        .summary-table td {
            white-space: nowrap;
            background: #fff;
            vertical-align: middle;
        }
        .table thead th {
            background: #f0f6fd;
            color: #153554;
            border-bottom: 0;
            font-weight: 700;
            white-space: nowrap;
        }
        .table td {
            vertical-align: middle;
            background: #fff;
        }
        .badge-estado {
            padding: 4px 10px;
            border-radius: 999px;
            font-weight: 700;
            font-size: .75rem;
            letter-spacing: .2px;
            text-transform: uppercase;
        }
        .badge-referido { background: #d8ecff; color: #0f4c81; }
        .badge-asignado { background: #dbf5ea; color: #0f7a53; }
        .badge-validado { background: #dbf5ea; color: #0f7a53; }
        .badge-rechazado { background: #ffe1e1; color: #b62929; }
        .badge-default { background: #eceff4; color: #4a5568; }
        .badge-alerta {
            padding: 4px 10px;
            border-radius: 999px;
            font-weight: 700;
            font-size: .72rem;
            letter-spacing: .2px;
            text-transform: uppercase;
            background: #ffe3c7;
            color: #8a3d00;
        }
        .brand-footer {
            margin-top: 16px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            text-align: center;
            color: var(--brand-primary);
            font-weight: 600;
            font-size: .9rem;
            padding: 10px 12px;
            box-shadow: 0 8px 20px rgba(15, 76, 129, 0.08);
        }
        .brand-footer a {
            color: inherit;
            text-decoration: none;
        }
        .brand-footer a:hover {
            text-decoration: underline;
        }
        .btn-edit {
            background: #0f4c81;
            border-color: #0f4c81;
            color: #fff;
            font-size: .75rem;
            padding: 4px 8px;
            border-radius: 8px;
            font-weight: 600;
        }
        .btn-edit:hover {
            color: #fff;
            background: #0c406c;
            border-color: #0c406c;
        }
        table.dataTable thead th,
        table.dataTable tbody td {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="brand-wrap">
    <div class="brand-card">
        <div class="brand-header">
            <div class="brand-title-row">
                <div class="brand-logo-wrap">
                    <img class="brand-logo" src="{{ asset('img/logo.png') }}" alt="Logo TestiApp">
                </div>
                <h4>Seguimiento de Referidos</h4>
            </div>
        </div>
        <div class="brand-body">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-warning">{{ session('error') }}</div>
            @endif
            <div class="top-action">
                <a class="btn-referir" href="{{ route('public.referidos.form', $token->token) }}">Referir Testigo</a>
            </div>
            <p class="mb-3">
                <strong>Municipio:</strong> {{ $municipio ?? 'N/D' }}
                | <strong>Comuna:</strong> {{ $token->comuna ?: 'Todas' }}
            </p>

            <div class="table-responsive table-wrap summary-grid">
                <h5>Resumen</h5>
                <table class="table table-sm mb-0 summary-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>Municipio</th>
                            <th>Puesto</th>
                            <th>Total</th>
                            <th>Remanentes</th>
                            <th>Registrados</th>
                            <th>Asignado</th>
                            <th>Validado</th>
                            <th>Faltan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $sumTotal = 0;
                            $sumRemanentes = 0;
                            $sumRegistrados = 0;
                            $sumAsignado = 0;
                            $sumValidado = 0;
                            $sumFaltan = 0;
                        @endphp
                        @forelse (($puestos_alcance ?? collect()) as $p)
                            @php
                                $sumTotal += (int) $p->mesas_total;
                                $sumRemanentes += (int) $p->remanentes_total;
                                $sumRegistrados += (int) $p->total_referidos;
                                $sumAsignado += (int) $p->c_asignado;
                                $sumValidado += (int) $p->c_validado;
                                $sumFaltan += (int) $p->faltantes;
                            @endphp
                            <tr>
                                <td>{{ $p->municipio ?? 'N/D' }}</td>
                                <td>{{ $p->puesto ?? 'N/D' }}</td>
                                <td>{{ $p->mesas_total }}</td>
                                <td>{{ $p->remanentes_total }}</td>
                                <td><strong>{{ $p->total_referidos }}</strong></td>
                                <td>{{ $p->c_asignado }}</td>
                                <td>{{ $p->c_validado }}</td>
                                <td><strong>{{ $p->faltantes }}</strong></td>
                            </tr>
                            @if ($loop->last)
                                <tr style="background:#eef5ff; font-weight:700;">
                                    <td colspan="2">TOTAL</td>
                                    <td>{{ $sumTotal }}</td>
                                    <td>{{ $sumRemanentes }}</td>
                                    <td>{{ $sumRegistrados }}</td>
                                    <td>{{ $sumAsignado }}</td>
                                    <td>{{ $sumValidado }}</td>
                                    <td>{{ $sumFaltan }}</td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">No hay puestos en el alcance actual.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="table-responsive table-wrap">
                <table id="referidosTable" class="table table-sm mb-0 display nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>Cédula</th>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th>Municipio</th>
                            <th>Puesto</th>
                            <th>Mesa</th>
                            <th>Estado</th>
                            <th>Observaciones</th>
                            <th>Alerta</th>
                            <th>Fecha</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($referidos as $r)
                            @php
                                $estado = strtolower((string) $r->estado);
                                $badgeClass = 'badge-default';
                                if ($estado === 'referido') $badgeClass = 'badge-referido';
                                elseif ($estado === 'asignado') $badgeClass = 'badge-asignado';
                                elseif ($estado === 'validado') $badgeClass = 'badge-validado';
                                elseif ($estado === 'rechazado') $badgeClass = 'badge-rechazado';
                                $obs = (string) ($r->observaciones ?? '');
                                $tieneCambioPuesto = stripos($obs, 'cambio de puesto') !== false;
                            @endphp
                            <tr>
                                <td>{{ $r->cedula }}</td>
                                <td>{{ $r->nombre }}</td>
                                <td>{{ $r->email }}</td>
                                <td>{{ $r->telefono }}</td>
                                <td>{{ $r->municipio }}</td>
                                <td>{{ $r->puesto }}</td>
                                <td>{{ $r->mesa_num }}</td>
                                <td><span class="badge-estado {{ $badgeClass }}">{{ $r->estado }}</span></td>
                                <td>{{ $r->observaciones }}</td>
                                <td>
                                    @if ($tieneCambioPuesto)
                                        <span class="badge-alerta">Cambio de puesto</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $r->created_at }}</td>
                                <td>
                                    <a class="btn btn-edit" href="{{ route('public.referidos.edit', [$token->token, $r->id]) }}">Editar</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="brand-footer">Creado por <a href="https://procesos.shipper.com.co" target="_blank" rel="noopener noreferrer">Jonathan Jimenez</a></div>
</div>
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.3.0/js/dataTables.responsive.min.js"></script>
<script>
    $(document).ready(function () {
        $('#referidosTable').DataTable({
            pageLength: 25,
            responsive: true,
            scrollX: true,
            order: [[10, 'desc']],
            columnDefs: [
                { responsivePriority: 1, targets: [0, 1, 7, 11] },
                { responsivePriority: 2, targets: [6, 10] }
            ],
            language: {
                search: 'Buscar:',
                lengthMenu: 'Mostrar _MENU_',
                info: 'Mostrando _START_ a _END_ de _TOTAL_',
                paginate: {
                    previous: 'Anterior',
                    next: 'Siguiente'
                }
            }
        });
    });
</script>
</body>
</html>
