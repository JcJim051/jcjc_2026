<div class="section-title">Datos personales</div>
<div class="row">
    <div class="col-md-4 form-group">
        <label>Nombre completo</label>
        <input type="text" name="nombre" class="form-control" value="{{ old('nombre', $abogado->nombre ?? '') }}" required>
    </div>
    @if($creating)
        <div class="col-md-4 form-group">
            <label>Cédula</label>
            <input type="text" name="cc" class="form-control" value="{{ old('cc') }}" required>
        </div>
    @else
        <div class="col-md-4 form-group">
            <label>Cédula</label>
            <input type="text" class="form-control" value="{{ $abogado->cc }}" readonly>
        </div>
    @endif
    <div class="col-md-4 form-group">
        <label>Correo electrónico</label>
        <input type="email" name="correo" class="form-control" value="{{ old('correo', $abogado->correo ?? '') }}" required>
    </div>
</div>
<div class="row">
    <div class="col-md-4 form-group">
        <label>Teléfono</label>
        <input type="text" name="telefono" class="form-control" value="{{ old('telefono', $abogado->telefono ?? '') }}" required>
    </div>
    <div class="col-md-4 form-group">
        <label>Dirección de residencia</label>
        <input type="text" name="direccion" class="form-control" value="{{ old('direccion', $abogado->direccion ?? '') }}" required>
    </div>
    <div class="col-md-4 form-group">
        <label>Comuna de residencia</label>
        <input type="text" name="comuna" class="form-control" value="{{ old('comuna', $abogado->comuna ?? '') }}" required>
    </div>
</div>
<div class="row">
    <div class="col-md-8 form-group">
        <label>Puesto donde vota</label>
        @php $selectedPuesto = old('puesto', $abogado->puesto ?? ''); @endphp
        <select name="puesto" class="form-control js-puesto" required>
            <option value="">Seleccione...</option>
            @foreach($puestos as $puesto)
                <option value="{{ $puesto }}" {{ $selectedPuesto === $puesto ? 'selected' : '' }}>{{ $puesto }}</option>
            @endforeach
            @if($selectedPuesto && !$puestos->contains($selectedPuesto))
                <option value="{{ $selectedPuesto }}" selected>{{ $selectedPuesto }}</option>
            @endif
        </select>
    </div>
    <div class="col-md-4 form-group">
        <label>Mesa de votación</label>
        <input type="text" name="mesa" class="form-control" value="{{ old('mesa', $abogado->mesa ?? '') }}" required>
    </div>
</div>

<div class="section-title">Formación y datos laborales</div>
<div class="row">
    <div class="col-md-6 form-group">
        <label>Nivel de estudios</label>
        <input type="text" name="estudios" class="form-control" value="{{ old('estudios', $abogado->estudios ?? '') }}">
    </div>
    <div class="col-md-6 form-group">
        <label>Título obtenido</label>
        <input type="text" name="titulo" class="form-control" value="{{ old('titulo', $abogado->titulo ?? '') }}">
    </div>
</div>
<div class="row">
    <div class="col-md-6 form-group">
        <label>Disponibilidad</label>
        <input type="text" name="disponibilidad" class="form-control" value="{{ old('disponibilidad', $abogado->disponibilidad ?? '') }}">
    </div>
    <div class="col-md-6 form-group">
        <label>Observaciones</label>
        <input type="text" name="observacion" class="form-control" value="{{ old('observacion', $abogado->observacion ?? '') }}">
    </div>
</div>

<div class="section-title">Soportes</div>
<div class="row">
    <div class="col-md-6 form-group">
        <label>Fotografía</label>
        <input type="file" name="foto" class="form-control-file" accept="image/jpeg,image/png,image/webp">
        <small class="text-muted">JPG, PNG o WebP. Máximo 4 MB.</small>
    </div>
    <div class="col-md-6 form-group">
        <label>Cédula en PDF</label>
        <input type="file" name="pdf_cc" class="form-control-file" accept=".pdf,application/pdf">
        <small class="text-muted">Máximo 4 MB.</small>
    </div>
</div>
