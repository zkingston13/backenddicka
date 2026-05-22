<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('lote_pallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('lotes')->onDelete('cascade');
            $table->integer('num_pallet'); // Número del pallet en el lote
            $table->integer('cantidad'); // Cantidad de contenedores en el pallet
            $table->integer('etiqueta_numero'); // Número de etiqueta (1/40, 2/40, etc.)
            $table->integer('etiqueta_total'); // Total de etiquetas generadas para ese lote
            $table->unique(['lote_id', 'num_pallet']); // Evita duplicados en un mismo lote
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('lote_pallets');
    }
};
