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
        Schema::create('menorpreco_produtos', function (Blueprint $table) {
            $table->id();

            $table->string('gtin', 20)->nullable()->unique();
            $table->string('palavrachave', 50); // era marca
            $table->string('descricao', 255);
            $table->string('ncm', 20);
            $table->integer('volume');
            $table->string('unidade', 5);

            // NOVAS COLUNAS
            $table->integer('categoria');
            $table->string('local');

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menorpreco_produtos');
    }
};
