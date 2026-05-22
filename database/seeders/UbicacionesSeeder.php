<?php

namespace Database\Seeders;

use App\Models\Ubicacion;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UbicacionesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
  public function run()
    {
        $racks = ['B','C','D','E','F','G','H','I','J'];

        foreach ($racks as $rack) {
            for ($nivel = 1; $nivel <= 6; $nivel++) {
                for ($pos = 1; $pos <= 132; $pos++) {
                    Ubicacion::create([
                        'rack' => $rack,
                        'nivel' => $nivel,
                        'posicion' => $pos,
                        'codigo' => sprintf("%s-%d-%03d", $rack, $nivel, $pos),
                        'estado' => 'Vacio'
                    ]);
                }
            }
        }
    }
}
