<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        Schema::table('lote_ubicacions', function (Blueprint $table) {
            // Obtén el nombre exacto de la clave foránea si existe
            $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_NAME = 'lote_ubicacions' 
                AND COLUMN_NAME = 'ubicacions_id' 
                AND CONSTRAINT_NAME <> 'PRIMARY'");

            if (!empty($foreignKeys)) {
                $constraintName = $foreignKeys[0]->CONSTRAINT_NAME;
                DB::statement("ALTER TABLE lote_ubicacions DROP FOREIGN KEY $constraintName");
            }

            // Elimina la columna si existe
            if (Schema::hasColumn('lote_ubicacions', 'ubicacions_id')) {
                $table->dropColumn('ubicacions_id');
            }

            // Agrega qr_ubicacion
            $table->string('qr_ubicacion')->after('lote_id');
        });
    }

    public function down()
    {
        Schema::table('lote_ubicacions', function (Blueprint $table) {
            // Restaurar la columna y la clave foránea
            $table->unsignedBigInteger('ubicacions_id')->nullable()->after('lote_id');
            $table->foreign('ubicacions_id')->references('id')->on('ubicacions')->onDelete('cascade');

            // Eliminar qr_ubicacion
            $table->dropColumn('qr_ubicacion');
        });
    }
};
