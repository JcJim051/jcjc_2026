<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">Control de pasos en links públicos</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.mesa_reportes.steps') }}" class="row align-items-end">
            @csrf
            <input type="hidden" name="eleccion_id" value="{{ $eleccionId }}">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="step_afluencia" name="habilitar_afluencia" value="1" {{ !empty($flujoConfig['afluencia']) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="step_afluencia">Afluencia</label>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="step_e14" name="habilitar_datos_e14" value="1" {{ !empty($flujoConfig['datos_e14']) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="step_e14">Datos E14</label>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="step_final" name="habilitar_informacion_final" value="1" {{ !empty($flujoConfig['informacion_final']) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="step_final">Información final</label>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="step_foto" name="habilitar_foto_e14" value="1" {{ !empty($flujoConfig['foto']) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="step_foto">Foto</label>
                </div>
            </div>
            <div class="col-12 d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                <small class="text-muted">La foto depende de Datos E14. Si además Información final está activa, la foto quedará al final del proceso.</small>
                <button type="submit" class="btn btn-primary btn-sm">Guardar visibilidad</button>
            </div>
        </form>
    </div>
</div>
