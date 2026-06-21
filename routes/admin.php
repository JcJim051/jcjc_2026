<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\ConsultorController;
use App\Http\Controllers\Admin\CoordinatorController;
use App\Http\Controllers\Admin\SuperUserController;
use App\Http\Controllers\Admin\TellerController;
use App\Http\Controllers\Admin\ResultadosController;
use App\Http\Controllers\Admin\EscrutinioController;
use App\Http\Controllers\Admin\DescargasController;
use App\Http\Controllers\Admin\TestigosController;
use App\Http\Controllers\Admin\ConsultasController;
use App\Http\Controllers\Admin\VerpuestosController;
use App\Http\Controllers\Admin\AniController;
use App\Http\Controllers\Admin\RevisionController;
use App\Http\Controllers\Admin\PosesionController;
use App\Http\Controllers\Admin\AsistenciaController;
use App\Http\Controllers\Admin\ZonalController;
use App\Http\Controllers\Admin\ZonalrController;
use App\Http\Controllers\Admin\MunicipalController;
use App\Http\Controllers\Admin\DepartamentalController;
use App\Http\Controllers\Admin\TableroController;
use App\Http\Controllers\Admin\VotantesController;
use App\Http\Controllers\Admin\AfluenciaController;
use App\Http\Controllers\Admin\QrController;
use App\Http\Controllers\Admin\ActualizarController;
use App\Http\Controllers\Admin\PmuController;
use App\Http\Controllers\Admin\FotosController;
use App\Http\Controllers\Admin\FotopmuController;
use App\Http\Controllers\Admin\TransmisionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\EleccionesController;
use App\Http\Controllers\Admin\TerritorioTokensController;
use App\Http\Controllers\Admin\ReferidosController;
use App\Http\Controllers\Admin\CandidatosController;
use App\Http\Controllers\Admin\ValidacionAniController;
use App\Http\Controllers\Admin\CneImportController;
use App\Http\Controllers\Admin\AbogadosController;
use App\Http\Controllers\Admin\ReunionesAbogadosController;
use App\Http\Controllers\Admin\BloqueosMesasController;
use App\Http\Controllers\Admin\DivipolController;
use App\Http\Controllers\Admin\AbogadoAccessTokenController;
use App\Http\Controllers\Admin\MesaReporteDashboardController;
use App\Http\Controllers\Admin\CoordinadoresOperativosController;

Route::get('', [AdminController::class, '__invoke'])->name('admin.home');

Route::resource('superusers', SuperUserController::class)->names('admin.superusers');
Route::resource('tellers', TellerController::class)->names('admin.tellers');
Route::resource('coordinators', CoordinatorController::class)->names('admin.coordinators');
Route::resource('consultors', ConsultorController::class)->names('admin.consultors');
Route::resource('resultados', ResultadosController::class)->names('admin.resultados');
Route::resource('escrutinio', EscrutinioController::class)->names('admin.escrutinio');
Route::resource('descargas', DescargasController::class)->names('admin.descargas');
Route::resource('testigos', TestigosController::class)->names('admin.testigos');
Route::resource('consultas', ConsultasController::class)->names('admin.consultas');
Route::resource('Verpuestos', VerpuestosController::class)->names('admin.verpuestos');
Route::resource('Ani', AniController::class)->names('admin.ani');
Route::resource('candidatos', CandidatosController::class)->names('admin.candidatos');
Route::resource('revision', RevisionController::class)->names('admin.revision');
Route::resource('posesion', PosesionController::class)->names('admin.posesion');
Route::resource('Asistencia', AsistenciaController::class)->names('admin.asistencia');
Route::resource('zonal', ZonalController::class)->names('admin.zonal');
Route::resource('zonalr', ZonalrController::class)->names('admin.zonalr');
Route::resource('municipal', MunicipalController::class)->names('admin.municipal');
Route::resource('departamental', DepartamentalController::class)->names('admin.departamental');
Route::resource('tablero', TableroController::class)->names('admin.tablero');
Route::resource('votantes', VotantesController::class)->names('admin.votantes');
Route::resource('afluencia', AfluenciaController::class)->names('admin.afluencia');
Route::resource('qr', QrController::class)->names('admin.qr');
Route::resource('fotos', FotosController::class)->names('admin.fotos');
Route::resource('fotopmu', FotosController::class)->names('admin.fotopmu');
Route::resource('transmision', TransmisionController::class)->names('admin.transmision');

Route::get('get-data', 'App\Http\Controllers\Admin\ConsultorController@getData')->name('getData');
Route::get('get-asistencia', 'App\Http\Controllers\Admin\AsistenciaController@getAsistencia')->name('getAsistencia');
Route::get('get-afluencia', 'App\Http\Controllers\Admin\AfluenciaController@getAfluencia')->name('getAfluencia');
Route::get('get-resultados', 'App\Http\Controllers\Admin\ResultadosController@getResultados')->name('getResultados');
Route::get('get-transmision', 'App\Http\Controllers\Admin\TransmisionController@getTransmision')->name('getTransmision');


Route::post('/actualizar-registros', 'App\Http\Controllers\Admin\ActualizarController@actualizarRegistros')->name('actualizarRegistros')->middleware('web');
Route::post('fotos-redimensionada', 'App\Http\Controllers\Admin\TellerController@fotos')->name('fotos');
Route::post('foto2-redimensionada', 'App\Http\Controllers\Admin\FotosController@segundafoto')->name('segundafoto');
Route::post('foto3-redimensionada', 'App\Http\Controllers\Admin\FotosController@reclamacion')->name('reclamacion');
Route::resource('pmu', PmuController::class)->names('admin.pmu');

Route::get('tellers/{teller}/edit1', 'App\Http\Controllers\Admin\TellerController@edit1')->name('admin.tellers.edit1');
Route::get('tellers/{teller}/edit2', 'App\Http\Controllers\Admin\TellerController@edit2')->name('admin.tellers.edit2');
Route::get('tellers/{teller}/edit3', 'App\Http\Controllers\Admin\TellerController@edit3')->name('admin.tellers.edit3');

Route::get('admin/users/import', [UserController::class, 'showImportForm'])->name('admin.users.import.form');
Route::get('admin/users/import/template', [UserController::class, 'downloadTemplate'])->name('admin.users.import.template');
Route::post('admin/users/import', [UserController::class, 'import'])->name('admin.users.import');

Route::resource('users', UserController::class)->names('admin.users');

Route::resource('roles', RoleController::class)->names('admin.roles');
Route::get('abogados/exportar/excel', [AbogadosController::class, 'exportar'])
    ->name('admin.abogados.exportar');
Route::middleware('can:Superuser')->group(function () {
    Route::post('abogados/actualizacion-personal/preview', [AbogadosController::class, 'previewActualizacionPersonal'])
        ->name('admin.abogados.actualizacion_personal.preview');
    Route::post('abogados/actualizacion-personal/apply', [AbogadosController::class, 'applyActualizacionPersonal'])
        ->name('admin.abogados.actualizacion_personal.apply');
    Route::post('abogados/adjuntos/preview', [AbogadosController::class, 'previewAdjuntos'])
        ->name('admin.abogados.adjuntos.preview');
    Route::post('abogados/adjuntos/process', [AbogadosController::class, 'processAdjuntos'])
        ->name('admin.abogados.adjuntos.process');
    Route::get('abogados/enlaces', [AbogadoAccessTokenController::class, 'index'])
        ->name('admin.abogado_tokens.index');
    Route::post('abogados/enlaces', [AbogadoAccessTokenController::class, 'store'])
        ->name('admin.abogado_tokens.store');
    Route::post('abogados/enlaces/{token}/toggle', [AbogadoAccessTokenController::class, 'toggle'])
        ->name('admin.abogado_tokens.toggle');
    Route::delete('abogados/enlaces/{token}', [AbogadoAccessTokenController::class, 'destroy'])
        ->name('admin.abogado_tokens.destroy');
});
Route::resource('abogados', AbogadosController::class)->names('admin.abogados');
Route::get('coordinadores-operativos', [CoordinadoresOperativosController::class, 'index'])->name('admin.coordinadores_operativos.index');
Route::get('coordinadores-operativos/exportar', [CoordinadoresOperativosController::class, 'export'])->name('admin.coordinadores_operativos.export');
Route::post('coordinadores-operativos/integrar', [CoordinadoresOperativosController::class, 'integrateExisting'])->name('admin.coordinadores_operativos.integrate');
Route::post('coordinadores-operativos/manual', [CoordinadoresOperativosController::class, 'storeManual'])->name('admin.coordinadores_operativos.store_manual');
Route::put('coordinadores-operativos/{coordinacion}', [CoordinadoresOperativosController::class, 'updateManual'])->name('admin.coordinadores_operativos.update_manual');
Route::post('abogados/{abogado}/asignar-coordinador', [AbogadosController::class, 'asignarCoordinador'])
    ->name('admin.abogados.asignar_coordinador');
Route::post('abogados/{abogado}/coordinaciones/{coordinacion}/liberar', [AbogadosController::class, 'liberarCoordinador'])
    ->name('admin.abogados.liberar_coordinador');
Route::post('abogados/import-sheet', [AbogadosController::class, 'importFromSheet'])
    ->name('admin.abogados.import_sheet');
Route::get('abogados/import-coordinadores/template', [AbogadosController::class, 'downloadCoordinatorTemplate'])
    ->name('admin.abogados.import_coordinadores.template');
Route::post('abogados/import-coordinadores', [AbogadosController::class, 'importCoordinadores'])
    ->name('admin.abogados.import_coordinadores');
Route::post('abogados/reubicar-coordinadores/preview', [AbogadosController::class, 'previewReubicarCoordinadores'])
    ->name('admin.abogados.reubicar_coordinadores.preview');
Route::post('abogados/reubicar-coordinadores/apply', [AbogadosController::class, 'applyReubicarCoordinadores'])
    ->name('admin.abogados.reubicar_coordinadores.apply');
Route::resource('abogados-reuniones', ReunionesAbogadosController::class)
    ->only(['index', 'create', 'store'])
    ->names('admin.abogados_reuniones');
Route::get('abogados-reuniones/{reunion}/qr', [ReunionesAbogadosController::class, 'qrAsistencia'])
    ->name('admin.abogados_reuniones.qr');

Route::get('/puntos/{mun}', [App\Http\Controllers\Admin\PuestosController::class, 'getByMunicipio'])
    ->name('admin.puntos.byMunicipio');

Route::get('elecciones', [EleccionesController::class, 'index'])->name('admin.elecciones.index');
Route::get('elecciones/plantilla-divipol', [EleccionesController::class, 'downloadTemplate'])
    ->name('admin.elecciones.divipol_template');
Route::post('elecciones', [EleccionesController::class, 'store'])->name('admin.elecciones.store');
Route::put('elecciones/{eleccion}', [EleccionesController::class, 'update'])->name('admin.elecciones.update');
Route::post('elecciones/{eleccion}/import-divipol', [EleccionesController::class, 'import'])->name('admin.elecciones.import');

Route::middleware('can:Superuser')->group(function () {
    Route::get('divipol', [DivipolController::class, 'index'])->name('admin.divipol.index');
    Route::get('divipol/{eleccion}/exportar', [DivipolController::class, 'export'])->name('admin.divipol.export');
    Route::post('divipol/{eleccion}/actualizar', [DivipolController::class, 'update'])->name('admin.divipol.update');
});

Route::get('territorio-tokens', [TerritorioTokensController::class, 'index'])->name('admin.territorio_tokens.index');
Route::get('territorio-tokens/export', [TerritorioTokensController::class, 'export'])->name('admin.territorio_tokens.export');
Route::post('territorio-tokens', [TerritorioTokensController::class, 'store'])->name('admin.territorio_tokens.store');
Route::post('territorio-tokens/bloquear-todos', [TerritorioTokensController::class, 'bloquearTodos'])->name('admin.territorio_tokens.bloquear_todos');
Route::get('territorio-tokens/municipios', [TerritorioTokensController::class, 'municipios'])->name('admin.territorio_tokens.municipios');
Route::get('territorio-tokens/comunas', [TerritorioTokensController::class, 'comunas'])->name('admin.territorio_tokens.comunas');
Route::get('territorio-tokens/zonas', [TerritorioTokensController::class, 'zonas'])->name('admin.territorio_tokens.zonas');
Route::get('territorio-tokens/{token}/proyeccion', [TerritorioTokensController::class, 'projection'])->name('admin.territorio_tokens.projection');
Route::post('territorio-tokens/{token}/toggle', [TerritorioTokensController::class, 'toggle'])->name('admin.territorio_tokens.toggle');
Route::put('territorio-tokens/{token}', [TerritorioTokensController::class, 'update'])->name('admin.territorio_tokens.update');
Route::delete('territorio-tokens/{token}', [TerritorioTokensController::class, 'destroy'])->name('admin.territorio_tokens.destroy');
Route::get('bloqueos-mesas', [BloqueosMesasController::class, 'index'])->name('admin.bloqueos_mesas.index');
Route::post('bloqueos-mesas/import', [BloqueosMesasController::class, 'import'])->name('admin.bloqueos_mesas.import');
Route::delete('bloqueos-mesas/{bloqueo}', [BloqueosMesasController::class, 'destroy'])->name('admin.bloqueos_mesas.destroy');

Route::get('referidos', [ReferidosController::class, 'index'])->name('admin.referidos.index');
Route::get('referidos/{referido}/asignar', [ReferidosController::class, 'asignarForm'])->name('admin.referidos.asignar.form');
Route::post('referidos/{referido}/asignar', [ReferidosController::class, 'asignar'])->name('admin.referidos.asignar');
Route::post('referidos/{referido}/asignar-postulado', [ReferidosController::class, 'asignarPostulado'])->name('admin.referidos.asignar_postulado');
Route::post('referidos/asignar-postulados-masivo', [ReferidosController::class, 'asignarPostuladosMasivo'])->name('admin.referidos.asignar_postulados_masivo');
Route::post('referidos/{referido}/rechazar', [ReferidosController::class, 'rechazar'])->name('admin.referidos.rechazar');
Route::post('referidos/{referido}/reactivar', [ReferidosController::class, 'reactivar'])->name('admin.referidos.reactivar');
Route::get('referidos/{referido}/mesas-disponibles', [ReferidosController::class, 'mesasDisponibles'])->name('admin.referidos.mesas_disponibles');
Route::post('referidos/{referido}/liberar', [ReferidosController::class, 'liberar'])->name('admin.referidos.liberar');
Route::post('referidos/export-meta', [ReferidosController::class, 'exportMeta'])->name('admin.referidos.export_meta');

Route::get('validacion-ani', [ValidacionAniController::class, 'index'])->name('admin.validacion_ani.index');
Route::post('validacion-ani/validar-masivo', [ValidacionAniController::class, 'validarMasivo'])->name('admin.validacion_ani.validar_masivo');
Route::get('validacion-ani/{referido}/edit', [ValidacionAniController::class, 'edit'])->name('admin.validacion_ani.edit');
Route::put('validacion-ani/{referido}', [ValidacionAniController::class, 'update'])->name('admin.validacion_ani.update');
Route::get('validacion-ani-coordinador/{coordinacion}/edit', [ValidacionAniController::class, 'editCoordinador'])->name('admin.validacion_ani.coordinador.edit');
Route::put('validacion-ani-coordinador/{coordinacion}', [ValidacionAniController::class, 'updateCoordinador'])->name('admin.validacion_ani.coordinador.update');

Route::get('cne-import', [CneImportController::class, 'index'])->name('admin.cne_import.index');
Route::post('cne-import/postulados', [CneImportController::class, 'importarPostulados'])->name('admin.cne_import.postulados');
Route::post('cne-import/acreditados', [CneImportController::class, 'importarAcreditados'])->name('admin.cne_import.acreditados');
Route::get('cne-import/asignados-pendientes', [CneImportController::class, 'exportarAsignadosPendientes'])->name('admin.cne_import.asignados_pendientes');
Route::post('cne-import/referidos-masivos/preview', [CneImportController::class, 'previewReferidosMasivos'])->name('admin.cne_import.referidos_masivos.preview');
Route::get('cne-import/referidos-masivos/{batch}', [CneImportController::class, 'showReferidosPreview'])->name('admin.cne_import.referidos_masivos.show');
Route::post('cne-import/referidos-masivos/{batch}/apply', [CneImportController::class, 'applyReferidosMasivos'])->name('admin.cne_import.referidos_masivos.apply');
Route::post('cne-import/referidos-masivos/{batch}/cancel', [CneImportController::class, 'cancelReferidosMasivos'])->name('admin.cne_import.referidos_masivos.cancel');
Route::get('cne-import/referidos-masivos/{batch}/errors', [CneImportController::class, 'downloadReferidosErrores'])->name('admin.cne_import.referidos_masivos.errors');

Route::middleware('can:dashboard-operativo-ver')->group(function () {
    Route::get('mesa-reportes/dashboard', [MesaReporteDashboardController::class, 'index'])->name('admin.mesa_reportes.dashboard');
    Route::get('mesa-reportes/afluencia', [MesaReporteDashboardController::class, 'afluencia'])->name('admin.mesa_reportes.afluencia');
    Route::get('mesa-reportes/e14', [MesaReporteDashboardController::class, 'e14'])->name('admin.mesa_reportes.e14');
    Route::get('mesa-reportes/mesas', [MesaReporteDashboardController::class, 'mesas'])->name('admin.mesa_reportes.mesas');
    Route::get('mesa-reportes/mesas/{mesa}', [MesaReporteDashboardController::class, 'showMesa'])->name('admin.mesa_reportes.mesas.show');
});

Route::middleware('can:dashboard-operativo-gestionar')->group(function () {
    Route::post('mesa-reportes/steps', [MesaReporteDashboardController::class, 'updateSteps'])->name('admin.mesa_reportes.steps');
    Route::get('mesa-reportes/afluencia/export-comunas', [MesaReporteDashboardController::class, 'exportAfluenciaComunas'])->name('admin.mesa_reportes.afluencia.export_comunas');
    Route::get('mesa-reportes/afluencia/export-puestos', [MesaReporteDashboardController::class, 'exportAfluenciaPuestos'])->name('admin.mesa_reportes.afluencia.export_puestos');
    Route::get('mesa-reportes/afluencia/export-mesas', [MesaReporteDashboardController::class, 'exportAfluenciaMesas'])->name('admin.mesa_reportes.afluencia.export_mesas');
    Route::get('mesa-reportes/afluencia/export-testigos-puestos', [MesaReporteDashboardController::class, 'exportAfluenciaTestigosPuestos'])->name('admin.mesa_reportes.afluencia.export_testigos_puestos');
    Route::get('mesa-reportes/e14/export-comunas', [MesaReporteDashboardController::class, 'exportE14Comunas'])->name('admin.mesa_reportes.e14.export_comunas');
    Route::get('mesa-reportes/e14/export-puestos', [MesaReporteDashboardController::class, 'exportE14Puestos'])->name('admin.mesa_reportes.e14.export_puestos');
    Route::get('mesa-reportes/e14/export-mesas', [MesaReporteDashboardController::class, 'exportE14Mesas'])->name('admin.mesa_reportes.e14.export_mesas');
    Route::get('mesa-reportes/mesas/export', [MesaReporteDashboardController::class, 'exportMesasAlertas'])->name('admin.mesa_reportes.mesas.export');
});
