<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Log;
use Illuminate\Database\QueryException;

class LogController extends Controller
{
    /**
     * Listar logs con paginación.
     */
    public function index()
    {
        try {
            $logs = Log::latest()->paginate(20);

            if ($logs->isEmpty()) {
                return response()->json(['message' => 'No hay registros en los logs'], 200);
            }

            return response()->json($logs, 200);
        } catch (QueryException $e) {
            return response()->json([
                'error' => 'Error en la base de datos',
                'detalles' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error inesperado',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un log específico por ID.
     */
    public function show($id)
    {
        try {
            $log = Log::find($id);

            if (!$log) {
                return response()->json(['error' => 'Log no encontrado'], 404);
            }

            return response()->json($log, 200);
        } catch (QueryException $e) {
            return response()->json([
                'error' => 'Error en la base de datos',
                'detalles' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error inesperado',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }
}
