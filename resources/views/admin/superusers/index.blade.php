@section('plugins.Datatables', true)
@extends('adminlte::page')

@section('title', 'Acreditar')

@section('content_header')
    {{--  <a href="{{route('admin.superusers.create')}}" class="float-right btn btn-secondary btn-sm">Agregar vendedor</a>  --}}
    <h1 style="text-align:center">Acreditacion de testigos electorales.</h1>
@stop

@section('content')
    @if (session('info'))
        <div class="alert alert-success">
            <strong>{{(session('info'))}}</strong>
        </div>
    @endif

    @if (($mesas ?? collect())->isEmpty())
        <div class="alert alert-warning">
            No hay mesas cargadas para la elección activa.
        </div>
    @endif
    

    <div class="card">
        <div class="card-body">
            <table id="example"
                class="table display nowrap table-bordered long-text"
                style="width:100%; font-size:10px">

                <thead class="text-white" style="background-color:hsl(209, 36%, 54%)">
                    <tr>
                        <th>#</th>
                        <th>DD</th>
                        <th>MM</th>
                        <th>ZZ</th>
                        <th>PP</th>
                        <th>Codpuesto</th>
                        <th>Municipio</th>
                        <th>Puesto</th>
                        <th>Mesa</th>
                        <th>candidato</th>
                        <th>Nombre</th>
                        <th>Tel</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($mesas as $r)

                        @php
                            $color = in_array($r->estado, ['asignado','validado','postulado','acreditado'], true)
                                ? 'rgb(0,169,14)'
                                : 'red';
                        @endphp

                        <tr>
                            <td>{{ $r->mesa_id ?? '-' }}</td>
                            <td>{{ $r->dd }}</td>
                            <td>{{ $r->mm }}</td>
                            <td>{{ $r->zz }}</td>
                            <td>{{ $r->pp }}</td>

                            <td style="color: {{ $color }}">
                                {{ $r->dd }}{{ $r->mm }}{{ $r->zz }}{{ $r->pp }}
                            </td>

                            <td style="color: {{ $color }}">
                                {{ $r->municipio }}
                            </td>

                            <td style="color: {{ $color }}">{{ $r->puesto }}</td>
                            <td style="color: {{ $color }}" data-order="{{ $r->mesa_sort ?? 0 }}">{{ $r->mesa_label }}</td>
                            <td style="color: {{ $color }}">-</td>
                            <td style="color: {{ $color }}">{{ $r->nombre ?? '-' }}</td>
                            <td style="color: {{ $color }}">{{ $r->telefono ?? '-' }}</td>

                            {{-- STATUS ICON --}}
                            <td style="font-size:20px; text-align:center">
                                @if(in_array($r->estado, ['asignado','validado','postulado','acreditado'], true))
                                    <i class="fas fa-vote-yea" style="color:rgb(22,161,22)">
                                        <p hidden>listo</p>
                                    </i>
                                @else
                                    <i class="fas fa-window-close" style="color:rgb(235,62,10)">
                                        <p hidden>Pendiente</p>
                                    </i>
                                @endif
                            </td>

                            {{-- ACTION --}}
                            <td>
                                @php
                                    $label = 'Referido';
                                    $class = 'primary';
                                    if ($r->estado === 'asignado') { $label = 'Asignado'; $class = 'primary'; }
                                    if ($r->estado === 'validado') { $label = 'Validado'; $class = 'success'; }
                                    if ($r->estado === 'postulado') { $label = 'Postulado'; $class = 'warning'; }
                                    if ($r->estado === 'acreditado') { $label = 'Acreditado'; $class = 'secondary'; }
                                @endphp

                                @if (!$r->referido_id)
                                    <span class="text-muted">-</span>
                                @elseif ($r->estado === 'acreditado')
                                    <a href="#" class="btn btn-secondary btn-sm">Acreditado</a>
                                @else
                                    <a href="{{ route('admin.superusers.edit', $r->referido_id) }}"
                                    class="btn btn-{{ $class }} btn-sm">
                                        {{ ($r->estado === 'validado' || Auth::user()->role == 4) ? 'Ver' : $label }}
                                    </a>
                                @endif
                            </td>
                        </tr>

                    @endforeach
                </tbody>
            </table>

        </div>
        
    </div>
    @if(isset($mesasPage))
        <div class="mt-3">
            {{ $mesasPage->links() }}
        </div>
    @endif



@stop

@section('css')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/searchpanes/2.2.0/css/searchPanes.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/select/1.7.0/css/select.dataTables.min.css">
@endsection



@section('js')
<script> console.log('de tu mano señor!'); </script>
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.3.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/searchpanes/2.2.0/js/dataTables.searchPanes.min.js"></script>
<script src="https://cdn.datatables.net/select/1.7.0/js/dataTables.select.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.2/js/buttons.print.min.js"></script>
    
{{-- <script>
    $(document).ready(function () {
       $('#example').DataTable({
             
            searchPanes: {
                layout: 'columns-8',
                initCollapsed: true
            },
            "pageLength": 25,
            
            "columnDefs": [
                {searchPanes: {show: true},targets: []},
                { target: 0, visible: false},
              

            ],
           
            "dom":'Prtip' ,
            
            "buttons": [
                {
               
                "extend": 'excelHtml5',
                "title": 'Alertas_preconteo_xls'
                 },
                 
            ],
            "language": { // Traducción al español
             "searchPanes": {
                "title": {
                    _: 'Filtros Aplicados - %d',
                    0: 'Sin filtros',
                    1: 'Un Filtro Aplicado'
                        }
                        // Agrega más traducciones aquí según tus necesidades
                 },
               
            },
            

            }
            );
        });






  
</script> --}}

    <script>
        $(document).ready(function () {
        
            let config = {
                pageLength: 25,
                responsive: true,
                columnDefs: [
                    { targets: 0, visible: false },
                    { targets: [1,2,3,4], visible: false },
                    @if(Auth::user()->role == 3)
                        { targets: 6, visible: false },
                    @endif
                ],
                order: [[1, 'asc'], [2, 'asc'], [3, 'asc'], [4, 'asc'], [8, 'asc']],
                language: {
                    search: "Buscar:"
                }
            };
        
            @if(Auth::user()->role == 1) // SUPERUSER
                config.dom = 'Prtip';
                config.searchPanes = {
                    layout: 'columns-8',
                    initCollapsed: true
                };
            @else
                config.dom = 'frtip'; // 🔥 buscador normal
            @endif
        
            $('#example').DataTable(config);
        });
    </script>
    
@endsection
