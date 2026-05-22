<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('lotes', function (Blueprint $table) {
            // Cambiar nombre de la columna 'numPiezas' a 'piezasPalet'
            $table->renameColumn('numPiezas', 'piezasPalet');

            // Agregar la columna 'piezasLote' como generada virtualmente
            $table->integer('piezasLote')->virtualAs('numPalets * piezasPalet');
        });
    }

    public function down()
    {
        Schema::table('lotes', function (Blueprint $table) {
            // Revertir los cambios en caso de rollback
            $table->renameColumn('piezasPalet', 'numPiezas');
            $table->dropColumn('piezasLote');
        });
    }
};
