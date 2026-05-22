<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('lote_ubicacions', function (Blueprint $table) {
            $table->dropColumn([
                'piezasRecibida',
                'totalUbicaciones',
                'piezasEntregadas',
                'piezasAlmacen',
                'usuario_id',
                'usuarioModificacion'
            ]);
        });
    }

    public function down()
    {
        Schema::table('lote_ubicacions', function (Blueprint $table) {
            $table->integer('piezasRecibida')->nullable();
            $table->integer('totalUbicaciones')->nullable();
            $table->integer('piezasEntregadas')->nullable();
            $table->integer('piezasAlmacen')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->unsignedBigInteger('usuarioModificacion')->nullable();
        });
    }
};
