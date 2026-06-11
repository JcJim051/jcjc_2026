<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AsistenciaReunionController;
use App\Http\Controllers\PublicAbogadoProfileController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('equipo-abogados')->middleware('throttle:20,1')->group(function () {
    Route::get('proyeccion/{token}', [PublicAbogadoProfileController::class, 'projection'])
        ->name('public.abogados.projection');
    Route::get('caracterizacion/{token}', [PublicAbogadoProfileController::class, 'characterizationForm'])
        ->name('public.abogados.characterization.form');
    Route::post('caracterizacion/{token}', [PublicAbogadoProfileController::class, 'characterizationStore'])
        ->name('public.abogados.characterization.store');

    Route::get('actualizar/{token}', [PublicAbogadoProfileController::class, 'updateIdentify'])
        ->name('public.abogados.update.identify');
    Route::post('actualizar/{token}/identificar', [PublicAbogadoProfileController::class, 'updateLookup'])
        ->middleware('throttle:10,1')
        ->name('public.abogados.update.lookup');
    Route::get('actualizar/{token}/datos', [PublicAbogadoProfileController::class, 'updateForm'])
        ->name('public.abogados.update.form');
    Route::put('actualizar/{token}/datos', [PublicAbogadoProfileController::class, 'updateStore'])
        ->name('public.abogados.update.store');
});

Route::get('referidos/{token}', [App\Http\Controllers\PublicReferidosController::class, 'form'])
    ->name('public.referidos.form');
Route::post('referidos/{token}', [App\Http\Controllers\PublicReferidosController::class, 'store'])
    ->name('public.referidos.store');
Route::get('referidos/{token}/seguimiento', [App\Http\Controllers\PublicReferidosController::class, 'seguimiento'])
    ->name('public.referidos.seguimiento');
Route::get('referidos/{token}/seguimiento/export-resumen', [App\Http\Controllers\PublicReferidosController::class, 'exportResumen'])
    ->name('public.referidos.export_resumen');
Route::get('referidos/{token}/seguimiento/{referido}/edit', [App\Http\Controllers\PublicReferidosController::class, 'edit'])
    ->name('public.referidos.edit');
Route::put('referidos/{token}/seguimiento/{referido}', [App\Http\Controllers\PublicReferidosController::class, 'update'])
    ->name('public.referidos.update');
Route::get('referidos/{token}/mesas-disponibles', [App\Http\Controllers\PublicReferidosController::class, 'mesasDisponibles'])
    ->name('public.referidos.mesas_disponibles');

Route::get('referidos/{token}/divipol/puestos', [App\Http\Controllers\PublicReferidosController::class, 'puestos'])
    ->name('public.referidos.puestos');
Route::get('referidos/{token}/divipol/departamentos', [App\Http\Controllers\PublicReferidosController::class, 'departamentos'])
    ->name('public.referidos.departamentos');
Route::get('referidos/{token}/divipol/municipios', [App\Http\Controllers\PublicReferidosController::class, 'municipios'])
    ->name('public.referidos.municipios');

Route::get('/asistencia/reunion/{token}/panel', [AsistenciaReunionController::class, 'panelReunion'])
    ->name('asistencia.reunion.panel');
Route::get('/asistencia/reunion/{token}', [AsistenciaReunionController::class, 'mostrarFormularioSesion'])
    ->middleware('signed:relative')
    ->name('asistencia.reunion.form');
Route::post('/asistencia/reunion/{token}', [AsistenciaReunionController::class, 'procesarFormularioSesion'])
    ->name('asistencia.reunion.submit');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified'
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');


});
