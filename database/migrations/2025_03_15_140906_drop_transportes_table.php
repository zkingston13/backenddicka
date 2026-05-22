<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::dropIfExists('transportes');
    }

    public function down()
    {
        Schema::create('transportes', function (Blueprint $table) {
            $table->id();
            $table->string('qrTransporte')->unique();
            $table->timestamps();
        });
    }
};
