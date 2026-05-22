<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Salida;
use App\Models\LoteUbicacion;
use App\Models\Lote;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\Log;


class SalidaController extends Controller
{
    /**
     * 📌 Obtener todas las salidas con sus relaciones
     */
    public function index()
    {
        try {
            // 🔹 Obtener las salidas con sus relaciones (Lote y Usuario)
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
                ->orderBy('created_at', 'desc') // 🔹 Ordenar del más reciente al más antiguo
                ->get();

            return response()->json($salidas, 200);
        } catch (Exception $e) {
            return response()->json([
                'error' => '❌ Error al obtener salidas',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 📌 Registrar una nueva salida
     */
    public function registrarSalida(Request $request)
    {
        try {
            // 🔹 Log para depuración
            Log::info('📥 Datos recibidos en la API:', $request->all());

            // 🔹 Validación de los datos recibidos
            $validatedData = $request->validate([
                'qrLote' => 'required|array',
                'qrLote.lote_id' => 'required|integer',
                'qrLote.pallet_numero' => 'required|integer|min:1',
                'qrEmbarque' => 'required|string|max:255',
                'paletPiso' => 'required|integer',
                'cantidadEntregada' => 'required|integer|min:1',
                'observaciones' => 'nullable|string',
            ]);

            // 🔹 Log para verificar los datos validados
            Log::info('✅ Datos validados correctamente:', $validatedData);

            // 🔹 Extraer valores desde `qrLote`
            $lote_id = $validatedData['qrLote']['lote_id'];
            $pallet_numero = $validatedData['qrLote']['pallet_numero'];

            // 🔹 Buscar el lote
            $lote = Lote::find($lote_id);
            if (!$lote) {
                Log::error('❌ Lote no encontrado', ['lote_id' => $lote_id]);
                return response()->json(['error' => '❌ Lote no encontrado'], 404);
            }

            // 🔹 Buscar el pallet en `lote_ubicacions`
            $loteUbicacion = LoteUbicacion::where('lote_id', $lote_id)
                ->where('pallet_numero', $pallet_numero)
                ->first();

            if (!$loteUbicacion) {
                Log::error('❌ El pallet no está registrado en una ubicación', [
                    'lote_id' => $lote_id,
                    'pallet_numero' => $pallet_numero
                ]);
                return response()->json(['error' => '❌ El pallet no está registrado en una ubicación'], 404);
            }

            // 🔹 Verificar que `piezasAlmacen` tenga suficientes piezas
            if (!isset($loteUbicacion->piezasAlmacen) || $validatedData['cantidadEntregada'] > $loteUbicacion->piezasAlmacen) {
                Log::error('❌ No hay suficientes piezas en este pallet', [
                    'piezasAlmacen' => $loteUbicacion->piezasAlmacen,
                    'cantidadEntregada' => $validatedData['cantidadEntregada']
                ]);
                return response()->json(['error' => '❌ No hay suficientes piezas en este pallet'], 400);
            }

            // 🔹 Obtener el usuario autenticado
            $usuario_id = \Illuminate\Support\Facades\Auth::id();
            if (!$usuario_id) {
                Log::error('❌ Usuario no autenticado');
                return response()->json(['error' => '❌ Usuario no autenticado'], 401);
            }

            // 🔹 Registrar la salida en `salidas`
            $salida = \App\Models\Salida::create([
                'qrEmbarque' => $validatedData['qrEmbarque'],
                'lote_id' => $lote_id,
                'paletPiso' => $validatedData['paletPiso'],
                'cantidadEntregada' => $validatedData['cantidadEntregada'],
                'observaciones' => $validatedData['observaciones'] ?? null,
                'usuario_id' => $usuario_id,
                'ultimaModificacion' => $usuario_id,
            ]);

            // 🔹 Log de salida registrada
            Log::info('✅ Salida registrada:', $salida->toArray());

            // 🔹 Restar la cantidad en el pallet específico
            $loteUbicacion->piezasAlmacen -= $validatedData['cantidadEntregada'];
            $loteUbicacion->usuarioModificacion = $usuario_id;
            $loteUbicacion->save();

            // 🔹 Si el pallet ya no tiene piezas, eliminar SOLO ese pallet en `lote_ubicacions`
            if ($loteUbicacion->piezasAlmacen == 0) {
                $loteUbicacion->delete();
                Log::info('🗑️ Pallet eliminado porque ya no tiene piezas', [
                    'lote_id' => $lote_id,
                    'pallet_numero' => $pallet_numero
                ]);
            }

            return response()->json(['message' => '✅ Salida registrada con éxito', 'salida' => $salida], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('❌ Error de validación', $e->errors());
            return response()->json(['error' => '❌ Error de validación', 'detalles' => $e->errors()], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('❌ Error en la base de datos', ['detalles' => $e->getMessage()]);
            return response()->json(['error' => '❌ Error en la base de datos', 'detalles' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            Log::error('❌ Error inesperado', ['detalles' => $e->getMessage()]);
            return response()->json(['error' => '❌ Error inesperado', 'detalles' => $e->getMessage()], 500);
        }
    }
}
