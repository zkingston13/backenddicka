<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoteUbicacion extends Model
{
    use HasFactory;

    protected $table = 'lote_ubicacions';
    protected $fillable = [
        'lote_id',
        'pallet_numero',
        'piezasAlmacen',
        'qr_ubicacion',
        'usuario_id',
        'usuarioModificacion',
    ];

    public function lote()
    {
        return $this->belongsTo(Lote::class, 'lote_id', 'id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function usuarioModificador()
    {
        return $this->belongsTo(Usuario::class, 'usuarioModificacion');
    }
}
