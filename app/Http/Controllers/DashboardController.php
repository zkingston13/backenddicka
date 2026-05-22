<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LoteUbicacion;
use App\Models\Lote;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Exception;

class DashboardController extends Controller
{
    /**
     * Obtener todas las estadísticas del dashboard.
     */
    public function obtenerEstadisticas()
    {
        try {
            // 1. Total de piezas en almacén (solo lotes con ubicación asignada)
            $totalPiezasAlmacen = Lote::whereIn('id', function ($query) {
                $query->select('lote_id')
                    ->from('lote_ubicacions')
                    ->distinct();
            })->sum('piezasLote');

            // 2. Total de ubicaciones ocupadas en almacén
            $ubicacionesOcupadas = DB::table('lote_ubicacions')
                ->selectRaw('COUNT(DISTINCT qr_ubicacion) as total')
                ->value('total');

            // 3. Cantidad total de productos en almacén por Lote
            // Partimos desde lote_ubicacions para que solo se consideren lotes con ubicaciones activas.
            $productosPorLote = DB::table('lote_ubicacions')
                ->join('lote_pallets', function ($join) {
                    $join->on('lote_ubicacions.lote_id', '=', 'lote_pallets.lote_id')
                        ->on('lote_ubicacions.pallet_numero', '=', 'lote_pallets.num_pallet');
                })
                ->join('lotes', 'lote_ubicacions.lote_id', '=', 'lotes.id')
                ->select('lotes.lote', DB::raw('SUM(lote_pallets.cantidad) as cantidad_total'))
                ->groupBy('lotes.lote')
                ->get();

            // 4. Productos en almacén con su ubicación
            // Se parte desde lote_ubicacions para asegurar que solo se muestran los registros activos
            $productosEnAlmacen = DB::table('lote_ubicacions')
                ->join('lotes', 'lote_ubicacions.lote_id', '=', 'lotes.id')
                ->join('productos', 'lotes.producto_id', '=', 'productos.id')
                ->join('lote_pallets', function ($join) {
                    $join->on('lote_ubicacions.lote_id', '=', 'lote_pallets.lote_id')
                        ->on('lote_ubicacions.pallet_numero', '=', 'lote_pallets.num_pallet');
                })
                ->select(
                    'lote_ubicacions.id',
                    'productos.sku',
                    'productos.nombre as producto',
                    'lotes.folio',
                    'lotes.lote',
                    'lote_ubicacions.qr_ubicacion as ubicacion',
                    'lote_pallets.cantidad as piezas'
                )
                ->groupBy(
                    'lote_ubicacions.id',
                    'productos.sku',
                    'productos.nombre',
                    'lotes.folio',
                    'lotes.lote',
                    'lote_ubicacions.qr_ubicacion',
                    'lote_pallets.cantidad'
                )
                ->orderBy('productos.nombre')
                ->get();

            // 🔥 Retornar toda la información en un solo JSON
            return response()->json([
                'totalPiezasAlmacen'   => $totalPiezasAlmacen,
                'ubicacionesOcupadas'  => $ubicacionesOcupadas,
                'productosPorLote'     => $productosPorLote,
                'productosEnAlmacen'   => $productosEnAlmacen
            ], 200);
        } catch (QueryException $e) {
            return response()->json(['error' => 'Error en la base de datos', 'detalles' => $e->getMessage()], 500);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }
}
