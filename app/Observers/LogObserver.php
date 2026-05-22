<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use App\Models\Log;
use Illuminate\Support\Facades\Auth;

class LogObserver
{
    /**
     * Registrar logs cuando se crea un registro.
     */
    public function created(Model $model)
    {
        try {
            Log::create([
                'accion' => 'create',
                'tabla_afectada' => $model->getTable(),
                'registro_id' => $model->id,
                'datos_nuevos' => $this->filtrarDatos($model->toArray()),
                'usuario_id' => Auth::id() ?? null, // Si no hay usuario autenticado, se registra como null
            ]);
        } catch (\Exception $e) {
            \Log::error("Error en LogObserver@created: " . $e->getMessage());
        }
    }

    /**
     * Registrar logs cuando se actualiza un registro.
     */
    public function updated(Model $model)
    {
        try {
            $datosAnteriores = $this->filtrarDatos($model->getOriginal());
            $datosNuevos = $this->filtrarDatos($model->getChanges());

            // Solo crear un log si realmente hubo cambios en los datos
            if (!empty($datosNuevos)) {
                Log::create([
                    'accion' => 'update',
                    'tabla_afectada' => $model->getTable(),
                    'registro_id' => $model->id,
                    'datos_anteriores' => $datosAnteriores,
                    'datos_nuevos' => $datosNuevos,
                    'usuario_id' => Auth::id() ?? null,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error("Error en LogObserver@updated: " . $e->getMessage());
        }
    }

    /**
     * Registrar logs cuando se elimina un registro.
     */
    public function deleted(Model $model)
    {
        try {
            Log::create([
                'accion' => 'delete',
                'tabla_afectada' => $model->getTable(),
                'registro_id' => $model->id,
                'datos_anteriores' => $this->filtrarDatos($model->toArray()),
                'usuario_id' => Auth::id() ?? null,
            ]);
        } catch (\Exception $e) {
            \Log::error("Error en LogObserver@deleted: " . $e->getMessage());
        }
    }

    /**
     * Filtrar datos sensibles antes de registrarlos en el log.
     */
    private function filtrarDatos(array $datos)
    {
        $datosSensibles = ['password', 'remember_token', 'created_at', 'updated_at'];
        return array_diff_key($datos, array_flip($datosSensibles));
    }
}
