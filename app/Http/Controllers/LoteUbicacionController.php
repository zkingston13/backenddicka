<?php

namespace App\Http\Controllers;

use App\Models\Ubicacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\LoteUbicacion;
use App\Models\Lote;
use Illuminate\Support\Facades\Auth;
use Exception;

class LoteUbicacionController extends Controller
{

    public function registrarUbicacion(Request $request)
    {
        try {
            // ✅ Verificar si el usuario está autenticado
            $usuario_id = Auth::id();
            if (!$usuario_id) {
                return response()->json(['error' => '❌ Usuario no autenticado'], 401);
            }

            // ✅ Validación de los datos recibidos
            $validatedData = $request->validate([
                'qrLote' => 'required|array',
                'qrLote.lote_id' => 'required|integer|exists:lotes,id',
                'qrLote.pallet_numero' => 'required|integer|min:1',
                'qrLote.cantidad' => 'required|integer|min:1',
                'qrUbicacion' => 'required|string|max:255'
            ]);

            // ✅ Buscar el lote en la base de datos
            $lote = Lote::find($validatedData['qrLote']['lote_id']);
            if (!$lote) {
                return response()->json(['error' => '❌ Lote no encontrado'], 404);
            }

            // ✅ Verificar si el pallet ya está registrado en la ubicación
            $loteUbicacion = LoteUbicacion::where('lote_id', $lote->id)
                ->where('qr_ubicacion', $validatedData['qrUbicacion'])
                ->where('pallet_numero', $validatedData['qrLote']['pallet_numero'])
                ->first();

            if ($loteUbicacion) {
                return response()->json(['message' => '⚠️ El pallet ya está registrado en esta ubicación'], 200);
            }

            // ✅ Registrar el pallet en la ubicación con usuario_id
            $nuevoRegistro = LoteUbicacion::create([
                'lote_id' => $lote->id,
                'pallet_numero' => $validatedData['qrLote']['pallet_numero'],
                'qr_ubicacion' => $validatedData['qrUbicacion'],
                'piezasAlmacen' => $validatedData['qrLote']['cantidad'], // Guardar cantidad de piezas
                'usuario_id' => $usuario_id, // ✅ Asignar usuario autenticado
                'usuarioModificacion' => null,
            ]);

            return response()->json([
                'message' => '✅ Ubicación registrada con éxito',
                'data' => $nuevoRegistro
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => '❌ Error de validación', 'detalles' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => '❌ Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }
    public function filtrarPorFolioYLote(Request $request)
    {
        try {
            // Validación de los filtros (opcional)
            $validatedData = $request->validate([
                'lote' => 'nullable|string|max:255',
                'folio' => 'nullable|integer',
            ]);

            // Consulta base: Solo los lotes que siguen en almacén
            $query = LoteUbicacion::with(['lote'])
                ->whereHas('lote', function ($q) {
                    $q->where('piezasPalet', '>', 0);
                });

            // Aplicar filtro por lote o folio
            if (!empty($validatedData['lote'])) {
                $query->whereHas('lote', function ($q) use ($validatedData) {
                    $q->where('lote', 'LIKE', '%' . $validatedData['lote'] . '%');
                });
            } elseif (!empty($validatedData['folio'])) {
                $query->whereHas('lote', function ($q) use ($validatedData) {
                    $q->where('folio', $validatedData['folio']);
                });
            }

            // Obtener los registros filtrados
            $resultados = $query->get();

            return response()->json(['data' => $resultados], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => '❌ Error de validación',
                'detalles' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => '❌ Error inesperado',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }


public function recomendarUbicacionLote($loteId)
{
    $lote = Lote::with('producto')->find($loteId);

    if (!$lote) {
        return response()->json(['error' => 'Lote no encontrado'], 404);
    }

    $productoId = $lote->producto_id;
    $paletsNecesarios = $lote->numPalets;

    $ubicacionesQuery = Ubicacion::where('estado', 'Vacio');
    $reglaAplicada = "General";

    // ================== REGLAS ESPECIALES ==================

    // 3 productos que van SOLO en niveles 1 (racks B-F)
    $productosNivel1 = [3, 17, 18];
    if (in_array($productoId, $productosNivel1)) {
        $ubicacionesQuery
            ->whereIn('rack', ['B','C','D','E','F'])
            ->where('nivel', 1);

        $reglaAplicada = "Nivel 1 racks B-F (producto especial)";
    }

    // Producto exclusivo en E nivel 2
    $productoEspecialE2 = 16;
    if ($productoId == $productoEspecialE2) {
        $ubicacionesQuery
            ->where('rack','E')
            ->where('nivel',2);

        $reglaAplicada = "Rack E nivel 2 (producto especial)";
    }

    // Productos exclusivos del nivel 2 del rack B
    $productosNivel2B = [2, 5, 7, 11];
    if (in_array($productoId, $productosNivel2B)) {
        $ubicacionesQuery
            ->where('rack','B')
            ->where('nivel',2);

        $reglaAplicada = "Rack B nivel 2 (producto especial)";
    }

    // ================== REGLA GENERAL ==================
    if ($reglaAplicada === "General") {

        // ❗Excluir racks/niveles especiales
        $ubicacionesQuery->where(function($q) use (
            $productosNivel1, $productoEspecialE2, $productosNivel2B
        ) {
            // Excluir nivel 1 racks B–F
            $q->whereNot(function($s) {
                $s->whereIn('rack', ['B','C','D','E','F'])
                  ->where('nivel', 1);
            });

            // Excluir E-2
            $q->whereNot(function($s) {
                $s->where('rack', 'E')
                  ->where('nivel', 2);
            });

            // Excluir B-2
            $q->whereNot(function($s) {
                $s->where('rack', 'B')
                  ->where('nivel', 2);
            });
        });
    }

    // Traer ubicaciones vacías
    $ubicaciones = $ubicacionesQuery->get();

    if ($ubicaciones->count() === 0) {
        return response()->json([
            'error' => "No hay ubicaciones disponibles",
            'reglaAplicada' => $reglaAplicada,
        ], 404);
    }

    // ================== ORDEN FINAL ==================
    // Orden REAL: rack → nivel → posición (1,3,2,4…)
    $ordenadas = $ubicaciones->sortBy(function ($u) {

        // Orden por rack según el orden B,C,D,E,F,G,H,I,J
        $ordenRacks = ['B'=>1,'C'=>2,'D'=>3,'E'=>4,'F'=>5,'G'=>6,'H'=>7,'I'=>8,'J'=>9];
        $rackOrden = $ordenRacks[$u->rack] ?? 99;

        // Nivel de menor a mayor
        $nivelOrden = $u->nivel;

        // Orden por posición 1,3,2,4,5,7,6,8...
        $grupo = intdiv($u->posicion - 1, 4);
        $map = [1,3,2,4];
        $posEnGrupo = ($u->posicion - 1) % 4;
        $ordenInterno = array_search($posEnGrupo + 1, $map);

        return sprintf("%02d-%02d-%04d", $rackOrden, $nivelOrden, ($grupo*10)+$ordenInterno);
    })->values();

    // Seleccionar la cantidad necesaria
    $recomendaciones = $ordenadas->take($paletsNecesarios);

    return response()->json([
        'mensaje' => "Regla aplicada: $reglaAplicada",
        'paletsSolicitados' => $paletsNecesarios,
        'recomendaciones' => $recomendaciones->map(function($u){
            return [
                'ubicacion' => $u->codigo,
                'rack' => $u->rack,
                'nivel' => $u->nivel,
                'posicion' => $u->posicion,
            ];
        })->values()
    ]);
}


public function terminarUbicacionLote($loteId)
{
    DB::beginTransaction();

    try {
        $lote = Lote::find($loteId);

        if (!$lote) {
            return response()->json(['error' => 'Lote no encontrado'], 404);
        }

        if ($lote->ubi === "Ubicado") {
            return response()->json(['error' => 'El lote ya está ubicado'], 400);
        }

        // Obtener recomendaciones del método actualizado
        $respuesta = $this->recomendarUbicacionLote($loteId)->getData();

        if (!isset($respuesta->recomendaciones) || count($respuesta->recomendaciones) == 0) {
            return response()->json(['error' => 'No hay ubicaciones disponibles'], 400);
        }

        $recomendaciones = collect($respuesta->recomendaciones);

        // Validar que existan suficientes ubicaciones
        if ($recomendaciones->count() < $lote->numPalets) {
            return response()->json([
                'error' => 'No hay ubicaciones suficientes para todos los palets',
                'necesarias' => $lote->numPalets,
                'disponibles' => $recomendaciones->count()
            ], 400);
        }

        // ========== ASIGNAR UBICACIONES ==========

        foreach ($recomendaciones->take($lote->numPalets) as $index => $u) {

            // Buscar la ubicación REAL en BD
            $ubicacion = Ubicacion::where('rack', $u->rack)
                ->where('nivel', $u->nivel)
                ->where('posicion', $u->posicion)
                ->lockForUpdate()
                ->first();

            if (!$ubicacion) {
                throw new \Exception("Ubicación no encontrada: {$u->ubicacion}");
            }

            if ($ubicacion->estado !== 'Vacio') {
                throw new \Exception("Ubicación ya ocupada inesperadamente: {$u->ubicacion}");
            }

            // Registrar la ubicación del palet del lote
            LoteUbicacion::create([
                'lote_id' => $lote->id,
                'pallet_numero' => $index + 1,
                'piezasAlmacen' => 0,
                'qr_ubicacion' => $ubicacion->codigo, // Asegúrate que existe el campo "codigo"
                'usuario_id' => auth()->id() ?? 1
            ]);

            // Marcar ubicación como ocupada
            $ubicacion->update([
                'estado' => 'Ocupado',
                'lote_id_ocupado' => $lote->id
            ]);
        }

        // ========== ACTUALIZAR EL LOTE ==========

        $lote->update([
            'ubi' => 'Ubicado',
            'usuarioModificacion' => auth()->id() ?? null
        ]);

        DB::commit();

        return response()->json([
            'message' => 'Lote ubicado correctamente',
            'lote' => $lote->id,
            'ubicacionesAsignadas' => $recomendaciones->take($lote->numPalets)->values()
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'error' => 'Error al ubicar el lote',
            'detalle' => $e->getMessage()
        ], 500);
    }
}



}
