<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $fillable = ['razonSocial', 'domicilio', 'usuario_id'];
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    // 🔹 Un cliente pertenece a un usuario (quien lo creó)
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    // 🔹 Un cliente pudo ser modificado por otro usuario
    public function usuarioModificador()
    {
        return $this->belongsTo(Usuario::class, 'usuarioModificacion');
    }
}
