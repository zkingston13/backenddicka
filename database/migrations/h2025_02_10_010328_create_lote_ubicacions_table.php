<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lote_ubicacions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lote_id');
            $table->unsignedBigInteger('ubicacions_id');
            $table->integer('piezasRecibida');
            $table->integer('totalUbicaciones');
            $table->integer('piezasEntregadas');
            $table->integer('piezasAlmacen');
            $table->unsignedBigInteger('usuario_id');
            $table->timestamp('usuarioModificacion')->nullable();
            $table->timestamps();

            $table->foreign('lote_id')->references('id')->on('lotes');
            $table->foreign('ubicacions_id')->references('id')->on('ubicacions');
            $table->foreign('usuario_id')->references('id')->on('usuarios');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lote_ubicacions');
    }
};
