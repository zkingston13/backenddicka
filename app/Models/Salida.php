<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Salida extends Model
{
    protected $fillable = [
        'qrEmbarque',
        'lote_id',
        'paletPiso',
        'cantidadEntregada',
        'observaciones',
        'usuario_id',
        'ultimaModificacion',
    ];

    protected $hidden = [
        'updated_at',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function lote()
    {
        return $this->belongsTo(Lote::class, 'lote_id');
    }
}
