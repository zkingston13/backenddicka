<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LotePallet extends Model
{
    use HasFactory;

    protected $table = 'lote_pallets'; // 🔹 Nombre de la tabla en la BD

    protected $fillable = [
        'lote_id',
        'num_pallet',
        'cantidad',
        'etiqueta_numero',
        'etiqueta_total'
    ];

    /**
     * Relación con el modelo Lote.
     */
    public function lote()
    {
        return $this->belongsTo(Lote::class);
    }
}
