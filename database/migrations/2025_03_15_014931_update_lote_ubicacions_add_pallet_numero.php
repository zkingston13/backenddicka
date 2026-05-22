<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('lote_ubicacions', function (Blueprint $table) {
            $table->integer('pallet_numero')->after('lote_id'); // 📌 Nuevo campo para identificar cada pallet
        });
    }

    public function down()
    {
        Schema::table('lote_ubicacions', function (Blueprint $table) {
            $table->dropColumn('pallet_numero');
        });
    }
};
