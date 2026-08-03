<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use App\Models\Salida;
use App\Models\LoteUbicacion;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Ubicacion;

class SalidaController extends Controller
{
    /**
     * Obtener todas las salidas
     */
    public function index()
    {
        try {
            $salidas = Salida::with(['lote', 'usuario'])
                ->select(
                    'id',
                    'lote_id',
                    'qrEmbarque',
                    'paletPiso',
                    'cantidadEntregada',
                    'observaciones',
                    'usuario_id',
                    'created_at'
                )
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($salidas, 200);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error al obtener salidas',
                'detalles' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Registrar salida manual por pallet
     */
    public function registrarSalida(Request $request)
    {
        try {
            Log::info('Datos recibidos en la API:', $request->all());

            $validatedData = $request->validate([
                'qrLote' => 'required|array',
                'qrLote.lote_id' => 'required|integer',
                'qrLote.pallet_numero' => 'required|integer|min:1',
                'qrEmbarque' => 'required|string|max:255',
                'paletPiso' => 'required|integer',
                'cantidadEntregada' => 'required|integer|min:1',
                'observaciones' => 'nullable|string',
            ]);

            $loteId = $validatedData['qrLote']['lote_id'];
            $palletNumero = $validatedData['qrLote']['pallet_numero'];

            $lote = Lote::find($loteId);

            if (!$lote) {
                return response()->json([
                    'error' => 'Lote no encontrado',
                ], 404);
            }

            $loteUbicacion = LoteUbicacion::where('lote_id', $loteId)
                ->where('pallet_numero', $palletNumero)
                ->first();

            if (!$loteUbicacion) {
                return response()->json([
                    'error' => 'El pallet no está registrado en una ubicación',
                ], 404);
            }

            if (
                !isset($loteUbicacion->piezasAlmacen) ||
                $validatedData['cantidadEntregada'] > $loteUbicacion->piezasAlmacen
            ) {
                return response()->json([
                    'error' => 'No hay suficientes piezas en este pallet',
                ], 400);
            }

            $usuarioId = Auth::id();

            if (!$usuarioId) {
                return response()->json([
                    'error' => 'Usuario no autenticado',
                ], 401);
            }

            $salida = Salida::create([
                'qrEmbarque' => $validatedData['qrEmbarque'],
                'lote_id' => $loteId,
                'paletPiso' => $validatedData['paletPiso'],
                'cantidadEntregada' => $validatedData['cantidadEntregada'],
                'observaciones' => $validatedData['observaciones'] ?? null,
                'usuario_id' => $usuarioId,
                'ultimaModificacion' => $usuarioId,
            ]);

            $loteUbicacion->piezasAlmacen -= $validatedData['cantidadEntregada'];
            $loteUbicacion->usuarioModificacion = $usuarioId;
            $loteUbicacion->save();

            if ((int) $loteUbicacion->piezasAlmacen === 0) {
                $loteUbicacion->delete();
            }

            return response()->json([
                'message' => 'Salida registrada con éxito',
                'salida' => $salida,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Error de validación',
                'detalles' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Error en la base de datos', [
                'detalles' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error en la base de datos',
                'detalles' => $e->getMessage(),
            ], 500);
        } catch (Exception $e) {
            Log::error('Error inesperado', [
                'detalles' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error inesperado',
                'detalles' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mandar un lote completo a salidas
     */
    public function moverLoteASalidas($id)
    {
        try {
            $resultado = DB::transaction(function () use ($id) {
                $lote = Lote::lockForUpdate()->find($id);

                if (!$lote) {
                    abort(404, 'Lote no encontrado');
                }

                $usuarioId = Auth::id();

                if (!$usuarioId) {
                    abort(401, 'Usuario no autenticado');
                }

                if ((int) $lote->en_salida === 1) {
                    abort(409, 'Este lote ya fue enviado a salidas');
                }

                $cantidadEntregada = (int) $lote->piezasLote;

                if ($cantidadEntregada <= 0) {
                    $cantidadEntregada =
                        (int) $lote->numPalets *
                        (int) $lote->piezasPalet;
                }

                if ($cantidadEntregada <= 0) {
                    abort(422, 'El lote no tiene una cantidad válida de piezas');
                }

                $salida = Salida::create([
                    'lote_id' => $lote->id,
                    'qrEmbarque' => $lote->folio,
                    'paletPiso' => (int) $lote->numPalets,
                    'cantidadEntregada' => $cantidadEntregada,
                    'observaciones' => $lote->observaciones,
                    'usuario_id' => $usuarioId,
                    'ultimaModificacion' => $usuarioId,
                ]);

$loteUbicaciones = LoteUbicacion::where('lote_id', $lote->id)->get();

$codigosUbicacion = $loteUbicaciones
    ->pluck('qr_ubicacion')
    ->filter()
    ->unique()
    ->values();

if ($codigosUbicacion->isNotEmpty()) {
    Ubicacion::whereIn('codigo', $codigosUbicacion)->update([
        'estado' => 'Vacio',
        'lote_id_ocupado' => null,
        'updated_at' => now(),
    ]);
}

$ubicacionesEliminadas = LoteUbicacion::where(
    'lote_id',
    $lote->id
)->delete();
                $lote->en_salida = 1;
                $lote->usuarioModificacion = $usuarioId;
                $lote->save();

                return [
                    'salida' => $salida,
                    'ubicaciones_eliminadas' => $ubicacionesEliminadas,
                ];
            });

            return response()->json([
                'message' => 'Lote enviado a salidas correctamente',
                'salida' => $resultado['salida'],
                'ubicaciones_eliminadas' => $resultado['ubicaciones_eliminadas'],
            ], 201);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (Exception $e) {
            Log::error('Error al mover el lote a salidas', [
                'lote_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al mover el lote a salidas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
