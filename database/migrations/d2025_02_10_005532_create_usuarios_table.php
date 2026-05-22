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
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('nombreUsuario', 30)->unique();
            $table->integer('numEmpleado')->unique();
            $table->string('email')->unique();
            $table->string('password', 250);
            $table->boolean('IsActive')->default(true);
            $table->unsignedBigInteger('rol_id');
            $table->timestamps();

            $table->foreign('rol_id')->references('id')->on('rols');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
