<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lote extends Model
{
    use HasFactory;

    protected $fillable = [
        'folio',
        'producto_id',
        'lote',
        'caducidad',
        'fechaRecibido',
        'numPalets',
        'piezasPalet',
        'unidadMedida',
        'operador',
        'lt',
        'placas',
        'observaciones',
        'usuario_id',
        'usuarioModificacion',
        'ubi'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function loteUbicaciones()
    {
        return $this->hasMany(LoteUbicacion::class, 'lote_id', 'id');
    }

    public function salidas()
    {
        return $this->hasMany(Salida::class, 'lote_id');
    }

    public function usuarioModificador()
    {
        return $this->belongsTo(Usuario::class, 'usuarioModificacion');
    }
}
