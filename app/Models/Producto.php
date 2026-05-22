<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Producto extends Model
{
    use HasFactory;
    protected $fillable = [
        //sku	nombre	cliente_id	propiedades	caracteristicas	usuario_id	usuarioModificacion
        'sku',
        'nombre',
        'cliente_id',
        'propiedades',
        'caracteristicas',
        'usuario_id',
        'usuarioModificacion'
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function lotes()
    {
        return $this->hasMany(Lote::class, 'producto_id');
    }
}
