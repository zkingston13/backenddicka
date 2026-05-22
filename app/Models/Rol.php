<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Rol extends Model
{
    use HasFactory;

    protected $table = 'rols';

    protected $fillable = ['puesto'];

    /**
     * Relación con los usuarios
     */
    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'rol_id', 'id');
    }
}
