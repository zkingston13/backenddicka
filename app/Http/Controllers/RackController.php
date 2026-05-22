<?php

namespace App\Http\Controllers;

use App\Models\Ubicacion;
use Illuminate\Http\Request;

class RackController extends Controller
{
 public function racks()
{
    try {
        $racks = Ubicacion::select('rack')
            ->distinct()
            ->orderBy('rack')
            ->get()
            ->map(function ($r) {
                return [
                    '_id'   => $r->rack,
                    'nombre'=> $r->rack
                ];
            });

        return response()->json($racks);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Error al obtener racks',
            'details' => $e->getMessage()
        ], 500);
    }
}

public function niveles($rack)
{
    try {
        $niveles = Ubicacion::where('rack', $rack)
            ->select('nivel')
            ->distinct()
            ->orderBy('nivel')
            ->pluck('nivel');

        return response()->json($niveles);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Error al obtener niveles',
            'details' => $e->getMessage()
        ], 500);
    }
}

public function ubicaciones($rack, $nivel)
{
    try {
        $ubicaciones = Ubicacion::where([
            'rack'  => $rack,
            'nivel' => $nivel
        ])
        ->orderBy('posicion', 'asc')
        ->get();

        return response()->json($ubicaciones);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Error al obtener ubicaciones',
            'details' => $e->getMessage()
        ], 500);
    }
}

public function update(Request $request, $id)
{
    try {
        $request->validate([
            'estado' => 'required|in:vacio,ocupado,mantenimiento'
        ]);

        $ubicacion = Ubicacion::find($id);

        if (!$ubicacion) {
            return response()->json(['error' => 'Ubicación no encontrada'], 404);
        }

        $ubicacion->estado = $request->estado;
        $ubicacion->save();

        return response()->json([
            'message' => 'Estado actualizado correctamente',
            'ubicacion' => $ubicacion
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Error al actualizar la ubicación',
            'details' => $e->getMessage()
        ], 500);
    }
}

}
