<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transporte extends Model
{
    public function salidas()
    {
        return $this->hasMany(Salida::class, 'transporte_id');
    }
}
