<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ubicacion extends Model
{
protected $table = 'ubicaciones';

    protected $fillable = [
        'rack',
        'nivel',
        'posicion',
        'codigo',
        'estado',
        'lote_id_ocupado'
    ];

    protected $casts = [
        'lote_id_ocupado' => 'integer'
    ];

    // Relación con lote
    public function lote()
    {
        return $this->belongsTo(Lote::class, 'lote_id_ocupado');
    }
}
