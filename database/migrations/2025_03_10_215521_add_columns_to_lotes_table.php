<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->integer('folio')->after('id');
            $table->date('fechaRecibido')->default(now())->after('unidadMedida');
            $table->text('observaciones')->nullable()->after('fechaRecibido');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropColumn(['folio', 'fechaRecibido', 'observaciones']);
        });
    }
};
