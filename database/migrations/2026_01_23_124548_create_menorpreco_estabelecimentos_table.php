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
        Schema::create('menorpreco_estabelecimentos', function (Blueprint $table) {
            $table->id();

            $table->string('codigo', 120)->unique();
            $table->string('nome_fantasia', 120)->nullable();
            $table->string('razao_social', 120)->nullable();
            $table->string('bairro', 80)->nullable();
            $table->string('cidade', 80);
            $table->char('uf', 2);

            // NOVAS COLUNAS DE ENDEREÇO
            $table->string('tp_logr', 30)->nullable(); // tipo de logradouro
            $table->string('nm_logr', 120)->nullable(); // nome do logradouro
            $table->string('nr_logr', 20)->nullable(); // número

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menorpreco_estabelecimentos');
    }
};
