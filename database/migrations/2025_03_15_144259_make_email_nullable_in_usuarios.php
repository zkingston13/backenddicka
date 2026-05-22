<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('usuarios', function (Blueprint $table) {
            // 🔹 Eliminar la restricción de unicidad antes de modificar la columna
            $table->dropUnique('usuarios_email_unique');

            // 🔹 Hacer la columna `email` nullable
            $table->string('email')->nullable()->change();

            // 🔹 Volver a agregar la restricción `unique`
            $table->unique('email');
        });
    }

    public function down()
    {
        Schema::table('usuarios', function (Blueprint $table) {
            // 🔹 Eliminar la restricción `unique`
            $table->dropUnique('usuarios_email_unique');

            // 🔹 Revertir la columna `email` a `NOT NULL`
            $table->string('email')->nullable(false)->unique()->change();
        });
    }
};
