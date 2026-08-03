<?php

use App\Http\Controllers\RackController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\LoteController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\SalidaController;
use App\Http\Controllers\TransporteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LoteUbicacionController;

// 📌 Autenticación
Route::post('/login', [AuthController::class, 'login']);

// 📌 Rutas de acceso libre (sin autenticación)
Route::get('/lote-ubicaciones/filtrar', [LoteUbicacionController::class, 'filtrarPorFolioYLote']);
Route::get('/dashboard/estadisticas', [DashboardController::class, 'obtenerEstadisticas']);
Route::post('/registrar-ubicacion', [LoteUbicacionController::class, 'registrarUbicacion'])->middleware('auth:sanctum');
// 1️⃣ Buscar por "lote"
Route::get('/lotes/buscar/{lote}', [LoteController::class, 'buscarPorLote']);

// 2️⃣ Mostrar todos los "lotes" con valor "Ubicado"
Route::get('/lotes/ubicados', [LoteController::class, 'lotesUbicados']);

// 3️⃣ Mostrar todos los "lotes" con valor "No Ubicado"
Route::get('/lotes/no-ubicados', [LoteController::class, 'lotesNoUbicados']);

// 4️⃣ Mostrar toda la info de un "lote" específico
Route::get('/lotes/{id}', [LoteController::class, 'detalleLote']);
  
 
Route::get('/lotes/{id}/etiquetas', [LoteController::class, 'imprimirEtiquetas']);
// 📌 Rutas protegidas con autenticación
Route::middleware(['auth:sanctum'])->group(function () {

    // 🔹 Cerrar sesión (actual o todas las sesiones)
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);

    // 🔹 Obtener Usuario Autenticado
    Route::get('/me', [AuthController::class, 'me']);

    // 🟢 Administración de Usuarios (Solo Administradores y Jefes de Operaciones)
    Route::middleware('role:Administrador,Jefe de operaciones')->group(function () {
        Route::apiResource('usuarios', UsuarioController::class);
    });

    // 🟢 Gestión de Clientes
    Route::apiResource('clientes', ClienteController::class);

    // 🟢 Gestión de Productos
    Route::apiResource('productos', ProductoController::class);

    

    // 🟢 Gestión de Lotes
    Route::apiResource('lotes', LoteController::class);
   
  Route::post('/lotes/{id}/salida', [
    SalidaController::class,
    'moverLoteASalidas'
]); 
      // 🟢 Buscar por "lote"
    Route::get('/lotes/buscar/{lote}', [LoteController::class, 'buscarPorLote']);

    // 🟢 Mostrar todos los "lotes" con valor "Ubicado"
    Route::get('/lotes/ubicados', [LoteController::class, 'lotesUbicados']);

    // 🟢 Mostrar todos los "lotes" con valor "No Ubicado"
    Route::get('/lotes/no-ubicados', [LoteController::class, 'lotesNoUbicados']);

    // 🟢 Mostrar toda la info de un "lote" específico
    Route::get('/lotes/{id}', [LoteController::class, 'detalleLote']);
    // Route::get('/lotes/{id}/recomendar', [LoteController::class, 'recomendarUbicacion']);
    Route::get('/lotes/{id}/recomendar', [LoteUbicacionController::class,'RecomendarUbicacionLote']);
    // 🟢 Cambiar el estatus de los lotes "No Ubicados"
    Route::put('/lotes/{id}/terminado', [LoteUbicacionController::class, 'terminarUbicacionLote']);
    Route::get(
    '/lotes/{loteId}/pallets/{palletNumero}/etiqueta',
    [LoteController::class, 'imprimirEtiquetaPallet']
);
    
    // 🟢 Gestión de Salidas
    Route::apiResource('salidas', SalidaController::class); // ✅ Mantiene todas las rutas REST
    Route::post('/registrar-salida', [SalidaController::class, 'registrarSalida']);

    // 🟢 Lista de racks
    Route::get('/racks', [RackController::class, 'racks']);

    // 🟢 Niveles de un rack
    Route::get('/racks/{id}/niveles', [RackController::class, 'niveles']);

    // 🟢 Ubicaciones de un nivel dentro de un rack
    Route::get('/racks/{rackId}/niveles/{nivel}/ubicaciones', [RackController::class, 'ubicaciones']);

    // 🟢 Actualizar estado de una ubicación
    Route::put('/ubicaciones/{id}', [RackController::class, 'update']);


    // 🟢 Logs y Auditoría
    Route::apiResource('logs', LogController::class)->only(['index', 'show']);

    // 🟢 Dashboard
    Route::get('/dashboard', [DashboardController::class, 'obtenerEstadoAlmacen']);

    Route::get('/inventario', [LoteController::class, 'inventarioGeneral']);
Route::get('/inventario/{sku}/lotes', [LoteController::class, 'desglosePorSku']);  
});


