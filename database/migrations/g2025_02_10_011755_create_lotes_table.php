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
        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('producto_id');
            $table->string('lote', 100);
            $table->string('caducidad', 50);
            $table->integer('numPalets');
            $table->integer('numPiezas');
            $table->string('unidadMedida', 20);
            $table->unsignedBigInteger('usuario_id');
            $table->timestamp('usuarioModificacion')->nullable();
            $table->timestamps();

            $table->foreign('producto_id')->references('id')->on('productos');
            $table->foreign('usuario_id')->references('id')->on('usuarios');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lotes');
    }
};
