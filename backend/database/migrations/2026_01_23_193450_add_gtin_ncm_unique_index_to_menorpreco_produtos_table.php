<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Remove índice único do GTIN usando SQL direto
        DB::statement('ALTER TABLE menorpreco_produtos DROP INDEX IF EXISTS menorpreco_produtos_gtin_unique');
        DB::statement('ALTER TABLE menorpreco_produtos DROP INDEX IF EXISTS gtin_unique');

        Schema::table('menorpreco_produtos', function (Blueprint $table) {
            // Cria índice único composto GTIN + NCM
            $table->unique(['gtin', 'ncm'], 'gtin_ncm_unique');
        });
    }

    public function down()
    {
        Schema::table('menorpreco_produtos', function (Blueprint $table) {
            $table->dropUnique('gtin_ncm_unique');
            $table->unique('gtin');
        });
    }
};
