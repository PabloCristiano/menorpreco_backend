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
        Schema::create('menorpreco_historico_precos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('produto_id')
                ->constrained('menorpreco_produtos')
                ->cascadeOnDelete();

            $table->foreignId('estabelecimento_id')
                ->constrained('menorpreco_estabelecimentos')
                ->cascadeOnDelete();

            $table->decimal('preco', 10, 2);
            $table->decimal('preco_tabela', 10, 2)->nullable();
            $table->decimal('desconto', 10, 2)->nullable();

            $table->decimal('distancia_km', 6, 2)->nullable();

            // Data da nota fiscal (vem da API)
            $table->dateTime('datahora_nota');

            // Data da coleta (1 registro por dia)
            $table->date('data_coleta');

            $table->timestamps();

            /**
             * 🔐 TRAVA ABSOLUTA CONTRA DUPLICAÇÃO
             * 1 produto + 1 estabelecimento + 1 dia = 1 registro
             */
            $table->unique(
                ['produto_id', 'estabelecimento_id', 'data_coleta'],
                'uniq_produto_estabelecimento_dia'
            );

            // Índices para performance
            $table->index(['produto_id', 'data_coleta']);
            $table->index(['estabelecimento_id']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menorpreco_historico_precos');
    }
};
