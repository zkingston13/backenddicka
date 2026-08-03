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
    try {
        $lote = Lote::with('producto')->find($loteId);

        if (!$lote) {
            return response()->json([
                'error' => 'Lote no encontrado'
            ], 404);
        }

        $productoId = $lote->producto_id;
        $paletsNecesarios = $lote->numPalets;

        // Ubicaciones que ya fueron confirmadas para este lote
        $confirmadas = LoteUbicacion::where('lote_id', $lote->id)
            ->orderBy('pallet_numero')
            ->get([
                'qr_ubicacion',
                'pallet_numero'
            ]);

        $totalConfirmadas = $confirmadas->count();
        $paletsPendientes = max(
            $paletsNecesarios - $totalConfirmadas,
            0
        );

        $ubicacionesQuery = Ubicacion::where('estado', 'Vacio');
        $reglaAplicada = 'General';

        // Productos que van solo en nivel 1, racks B-F
        $productosNivel1 = [3, 17, 18];

        if (in_array($productoId, $productosNivel1)) {
            $ubicacionesQuery
                ->whereIn('rack', ['B', 'C', 'D', 'E', 'F'])
                ->where('nivel', 1);

            $reglaAplicada =
                'Nivel 1 racks B-F (producto especial)';
        }

        // Producto exclusivo en E nivel 2
        $productoEspecialE2 = 16;

        if ($productoId == $productoEspecialE2) {
            $ubicacionesQuery
                ->where('rack', 'E')
                ->where('nivel', 2);

            $reglaAplicada =
                'Rack E nivel 2 (producto especial)';
        }

        // Productos exclusivos del nivel 2 del rack B
        $productosNivel2B = [2, 5, 7, 11];

        if (in_array($productoId, $productosNivel2B)) {
            $ubicacionesQuery
                ->where('rack', 'B')
                ->where('nivel', 2);

            $reglaAplicada =
                'Rack B nivel 2 (producto especial)';
        }

        // Regla general
        if ($reglaAplicada === 'General') {
            $ubicacionesQuery->where(function ($q) {
                // Excluir nivel 1 de racks B-F
                $q->whereNot(function ($subQuery) {
                    $subQuery
                        ->whereIn(
                            'rack',
                            ['B', 'C', 'D', 'E', 'F']
                        )
                        ->where('nivel', 1);
                });

                // Excluir rack E, nivel 2
                $q->whereNot(function ($subQuery) {
                    $subQuery
                        ->where('rack', 'E')
                        ->where('nivel', 2);
                });

                // Excluir rack B, nivel 2
                $q->whereNot(function ($subQuery) {
                    $subQuery
                        ->where('rack', 'B')
                        ->where('nivel', 2);
                });
            });
        }

        $ubicaciones = $ubicacionesQuery->get();

        if (
            $ubicaciones->isEmpty() &&
            $paletsPendientes > 0
        ) {
            return response()->json([
                'error' => 'No hay ubicaciones disponibles',
                'reglaAplicada' => $reglaAplicada,
                'confirmadas' => $confirmadas->map(
                    function ($registro) {
                        return [
                            'ubicacion' =>
                                $registro->qr_ubicacion,
                            'pallet_numero' =>
                                $registro->pallet_numero,
                            'ocupado' => true
                        ];
                    }
                )->values()
            ], 404);
        }

        // Orden rack → nivel → posición
        $ordenadas = $ubicaciones
            ->sortBy(function ($ubicacion) {
                $ordenRacks = [
                    'B' => 1,
                    'C' => 2,
                    'D' => 3,
                    'E' => 4,
                    'F' => 5,
                    'G' => 6,
                    'H' => 7,
                    'I' => 8,
                    'J' => 9
                ];

                $rackOrden =
                    $ordenRacks[$ubicacion->rack] ?? 99;

                $nivelOrden = $ubicacion->nivel;

                $grupo = intdiv(
                    $ubicacion->posicion - 1,
                    4
                );

                $mapa = [1, 3, 2, 4];

                $posicionEnGrupo =
                    ($ubicacion->posicion - 1) % 4;

                $ordenInterno = array_search(
                    $posicionEnGrupo + 1,
                    $mapa
                );

                return sprintf(
                    '%02d-%02d-%04d',
                    $rackOrden,
                    $nivelOrden,
                    ($grupo * 10) + $ordenInterno
                );
            })
            ->values();

        $pendientes = $ordenadas
            ->take($paletsPendientes)
            ->map(function ($ubicacion) {
                return [
                    'ubicacion' => $ubicacion->codigo,
                    'rack' => $ubicacion->rack,
                    'nivel' => $ubicacion->nivel,
                    'posicion' => $ubicacion->posicion,
                    'ocupado' => false
                ];
            });

        $ocupadas = $confirmadas->map(
            function ($registro) {
                return [
                    'ubicacion' =>
                        $registro->qr_ubicacion,
                    'pallet_numero' =>
                        $registro->pallet_numero,
                    'ocupado' => true
                ];
            }
        );

        // Primero muestra las ya confirmadas y después las pendientes
        $recomendaciones = $ocupadas
            ->concat($pendientes)
            ->values();

        return response()->json([
            'mensaje' =>
                "Regla aplicada: {$reglaAplicada}",
            'paletsSolicitados' => $paletsNecesarios,
            'paletsConfirmados' => $totalConfirmadas,
            'paletsPendientes' => $paletsPendientes,
            'completado' =>
                $totalConfirmadas >= $paletsNecesarios,
            'recomendaciones' => $recomendaciones
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'error' =>
                'Error al generar recomendaciones',
            'detalle' => $e->getMessage()
        ], 500);
    }
}

public function terminarUbicacionLote(
    Request $request,
    $loteId
) {
    $validatedData = $request->validate([
        'ubicacion' => 'required|string'
    ]);

    DB::beginTransaction();

    try {
        $lote = Lote::find($loteId);

        if (!$lote) {
            DB::rollBack();

            return response()->json([
                'error' => 'Lote no encontrado'
            ], 404);
        }

        if ($lote->ubi === 'Ubicado') {
            DB::rollBack();

            return response()->json([
                'error' =>
                    'El lote ya está completamente ubicado'
            ], 400);
        }

        $totalActual = LoteUbicacion::where(
            'lote_id',
            $lote->id
        )->count();

        if ($totalActual >= $lote->numPalets) {
            $lote->update([
                'ubi' => 'Ubicado',
                'usuarioModificacion' =>
                    auth()->id()
            ]);

            DB::commit();

            return response()->json([
                'error' =>
                    'Todos los pallets ya fueron ubicados',
                'completado' => true,
                'estado_lote' => 'Ubicado'
            ], 400);
        }

        // Bloquear la posición seleccionada
        $ubicacion = Ubicacion::where(
            'codigo',
            $validatedData['ubicacion']
        )
            ->lockForUpdate()
            ->first();

        if (!$ubicacion) {
            DB::rollBack();

            return response()->json([
                'error' => 'Ubicación no encontrada'
            ], 404);
        }

        if ($ubicacion->estado !== 'Vacio') {
            DB::rollBack();

            return response()->json([
                'error' =>
                    'La ubicación ya está ocupada'
            ], 400);
        }

        $yaRegistrada = LoteUbicacion::where(
            'lote_id',
            $lote->id
        )
            ->where(
                'qr_ubicacion',
                $ubicacion->codigo
            )
            ->exists();

        if ($yaRegistrada) {
            DB::rollBack();

            return response()->json([
                'error' =>
                    'La ubicación ya fue registrada para este lote'
            ], 400);
        }

        $palletNumero = $totalActual + 1;

        $registro = LoteUbicacion::create([
            'lote_id' => $lote->id,
            'pallet_numero' => $palletNumero,
            'piezasAlmacen' =>
                $lote->piezasPalet ?? 0,
            'qr_ubicacion' => $ubicacion->codigo,
            'usuario_id' => auth()->id() ?? 1,
            'usuarioModificacion' => null
        ]);

        // Solo la ubicación física se marca como Ocupado
        $ubicacion->update([
            'estado' => 'Ocupado',
            'lote_id_ocupado' => $lote->id
        ]);

        $totalRegistrados = LoteUbicacion::where(
            'lote_id',
            $lote->id
        )->count();

        $completado =
            $totalRegistrados >= $lote->numPalets;

        // El lote sigue como No Ubicado mientras falten pallets
        $lote->update([
            'ubi' =>
                $completado
                    ? 'Ubicado'
                    : 'No Ubicado',
            'usuarioModificacion' =>
                auth()->id()
        ]);

        DB::commit();

        return response()->json([
            'message' =>
                $completado
                    ? 'Todos los pallets fueron ubicados correctamente'
                    : 'Ubicación confirmada correctamente',
            'ubicacion' => $ubicacion->codigo,
            'pallet_numero' => $palletNumero,
            'total_registrados' =>
                $totalRegistrados,
            'total_palets' => $lote->numPalets,
            'completado' => $completado,
            'estado_lote' =>
                $completado
                    ? 'Ubicado'
                    : 'No Ubicado',
            'registro' => $registro
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'error' => 'Error al ubicar el lote',
            'detalle' => $e->getMessage()
        ], 500);
    }
}
}
