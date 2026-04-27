<?php

use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\MobileE14Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/mobile/login', [MobileAuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/mobile/logout', [MobileAuthController::class, 'logout']);

    Route::get('/mobile/e14/sellers', [MobileE14Controller::class, 'sellers']);
    Route::get('/mobile/e14/form', [MobileE14Controller::class, 'form']);
    Route::post('/mobile/e14/submit', [MobileE14Controller::class, 'submit']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
