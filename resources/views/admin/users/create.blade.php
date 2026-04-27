@extends('adminlte::page')

@section('title', 'Crear Usuario')

@section('content')
<br>
@if ($errors->any())
    <div class="m-3 alert alert-danger">
        <strong>Ups, hay errores:</strong>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="mt-4 d-flex justify-content-center">
    <div class="card" style="width: 800px;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Crear Usuario</h5>
            <div>
                <label for="status" class="mb-0 mr-2">Estado:</label>
                <select name="status"
                        id="status"
                        form="formUser"
                        class="form-control form-control-sm d-inline-block"
                        style="width: 120px;">
                    <option value="1" {{ old('status', 1) == 1 ? 'selected' : '' }}>Activo</option>
                    <option value="0" {{ old('status') == 0 ? 'selected' : '' }}>Bloqueado</option>
                </select>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label>Candidato(s)</label>
            
                    @php
                        $candidatosSeleccionados = old(
                            'candidatos',
                            isset($user) && $user->candidatos
                                ? explode(',', $user->candidatos)
                                : []
                        );
                    @endphp
            
                    <select name="candidatos[]"
                            id="candidatos"
                            class="form-control select2"
                            form="formUser"
                            multiple>
                        <option value="0"
                            {{ in_array('0', $candidatosSeleccionados) ? 'selected' : '' }}>
                            General
                        </option>

                        @foreach ($candidatos as $c)
                            <option value="{{ $c->codigo }}"
                                {{ in_array((string) $c->codigo, $candidatosSeleccionados) ? 'selected' : '' }}>
                                {{ $c->codigo }} - {{ $c->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            
            
            
        </div>

        <form id="formUser" action="{{ route('admin.users.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <div class="row">

                    {{-- Nombre --}}
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Nombre</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                    </div>

                    {{-- Email --}}
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                    </div>

                    {{-- Password --}}
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Contraseña</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                    </div>

                    {{-- Rol --}}
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Rol</label>
                            <select name="role" id="role" class="form-control" required>
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Municipios --}}
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Municipio(s)</label>
                            @php $munSeleccionados = old('mun', $user->mun ?? []); @endphp
                    
                            <select name="mun[]" id="mun" class="form-control select2" multiple>
                                <option value="all">Todos</option> {{-- Opción especial --}}
                                @foreach($municipios as $m)
                                    <option value="{{ $m->codmun }}"
                                        {{ in_array($m->codmun, $munSeleccionados) ? 'selected' : '' }}>
                                        {{ $m->codmun }} - {{ $m->municipio }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @push('js')
                        <script>
                        $(document).ready(function() {
                            let $select = $('#mun');

                            $select.select2({
                                placeholder: "Seleccione municipios",
                                width: '100%'
                            });

                            // Si selecciona "Todos"
                            $select.on('select2:select', function(e) {
                                if (e.params.data.id === 'all') {
                                    // Selecciona todos los options
                                    let allValues = [];
                                    $select.find('option').each(function() {
                                        if ($(this).val() !== 'all') allValues.push($(this).val());
                                    });
                                    $select.val(allValues).trigger('change.select2');
                                }
                            });

                            // Si deselecciona "Todos"
                            $select.on('select2:unselect', function(e) {
                                if (e.params.data.id === 'all') {
                                    $select.val(null).trigger('change.select2');
                                }
                            });
                        });
                        </script>
                    @endpush
                    {{-- Puestos --}}
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Puesto(s)</label>
                            <select id="codpuesto" name="codpuesto[]" class="form-control select2" multiple>
                                <option value="">Seleccione municipio(s) primero...</option>
                            </select>
                        </div>
                    </div>

                    {{-- Codzon --}}
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Codigo Zona (codzon)</label>
                            <input type="text" id="codzon" name="codzon" class="form-control" readonly>
                        </div>
                    </div>

                </div>
            </div>

            <div class="text-right card-footer">
                <button type="submit" class="btn btn-success">Crear</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection

@section('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script>
$(document).ready(function(){

    $('.select2').select2({
        placeholder: "Seleccione opciones",
        width: '100%'
    });

    function configurarCamposPorRol() {
        var rol = $('#role').val();

        if (rol == 1) { // ADMIN
            $('#mun').prop('disabled', false);
            $('#codpuesto').prop('disabled', true).val(null).trigger('change');
             $('#codzon').prop('readonly', false);
        } 
        else if (rol == 3) { // COORDINADOR
            $('#mun').prop('disabled', false);
            $('#codpuesto').prop('disabled', false);
        } 
        else {
            $('#mun').prop('disabled', false);
            $('#codpuesto').prop('disabled', true).val(null).trigger('change');
        }
    }

    configurarCamposPorRol();
    $('#role').on('change', configurarCamposPorRol);

    $('#mun').on('change', function(){
        var rol = $('#role').val();
        if (rol != 3) return; // Solo coordinador carga puestos

        var municipios = $(this).val();
        var $codpuesto = $('#codpuesto');

        $codpuesto.empty().append('<option>Cargando...</option>');

        if(municipios && municipios.length > 0){
            $codpuesto.empty();

            municipios.forEach(function(mun){
                $.get('/admin/puntos/' + mun)
                    .done(function(data){
                        data.forEach(function(p){
                            if ($codpuesto.find("option[value='"+p.codpuesto+"']").length === 0) {
                                $codpuesto.append('<option value="'+p.codpuesto+'">'+p.codpuesto+' - '+p.nombre+'</option>');
                            }
                        });
                    });
            });

        } else {
            $codpuesto.empty().append('<option value="">Seleccione municipio(s) primero...</option>');
        }
    });

});
</script>
@endsection
