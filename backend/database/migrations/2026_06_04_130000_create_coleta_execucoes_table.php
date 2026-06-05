<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coleta_execucoes', function (Blueprint $table) {
            $table->id();

            $table->string('gatilho')->default('manual');   // manual | agendado
            $table->string('status')->default('rodando');    // rodando | sucesso | erro

            $table->timestamp('iniciado_em');
            $table->timestamp('finalizado_em')->nullable();
            $table->decimal('duracao_seg', 10, 2)->nullable();

            $table->unsignedInteger('total_grupos')->default(0);
            $table->unsignedInteger('total_chamadas_api')->default(0);
            $table->unsignedInteger('total_processados')->default(0);
            $table->unsignedInteger('total_erros')->default(0);

            $table->text('mensagem_erro')->nullable();

            $table->timestamps();

            $table->index('iniciado_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coleta_execucoes');
    }
};
