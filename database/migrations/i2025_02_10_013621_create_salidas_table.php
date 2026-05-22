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
        Schema::create('salidas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transporte_id');
            $table->unsignedBigInteger('lote_id');
            $table->integer('paletPiso');
            $table->integer('cantidadEntregada');
            $table->unsignedBigInteger('usuario_id');
            $table->timestamp('ultimaModificacion')->nullable();
            $table->timestamps();

            $table->foreign('transporte_id')->references('id')->on('transportes');
            $table->foreign('lote_id')->references('id')->on('lotes');
            $table->foreign('usuario_id')->references('id')->on('usuarios');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salidas');
    }
};
