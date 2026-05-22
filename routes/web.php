<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\LoteController;

Route::get('/', function () {
    return view('welcome');
});

// 🔹 Rutas de prueba para Lotes en `web.php`
// Route::middleware(['web'])->group(function () {
//     Route::get('/lotes', [LoteController::class, 'index']);
//     Route::post('/lotes', [LoteController::class, 'store'])->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
//     Route::get('/lotes/{id}', [LoteController::class, 'show']);
//     Route::put('/lotes/{id}', [LoteController::class, 'update'])->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
//     Route::delete('/lotes/{id}', [LoteController::class, 'destroy'])->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
//     Route::post('/lotes/{id}/imprimir-etiqueta', [LoteController::class, 'imprimirEtiqueta'])->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
// });
