<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('salidas', function (Blueprint $table) {
            // ✅ Agregar nueva columna para el QR del embarque
            $table->string('qrEmbarque')->after('lote_id')->nullable();

            // ✅ Agregar columna de observaciones
            $table->text('observaciones')->after('cantidadEntregada')->nullable();

            // ✅ Eliminar la clave foránea y la columna transporte_id
            $table->dropForeign(['transporte_id']);
            $table->dropColumn('transporte_id');
        });
    }

    public function down()
    {
        Schema::table('salidas', function (Blueprint $table) {
            // Revertir cambios en caso de rollback
            $table->unsignedBigInteger('transporte_id')->nullable();
            $table->foreign('transporte_id')->references('id')->on('transportes')->onDelete('cascade');

            $table->dropColumn('qrEmbarque');
            $table->dropColumn('observaciones');
        });
    }
};
