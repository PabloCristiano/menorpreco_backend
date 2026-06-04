<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lista_produtos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('produto_id')
                ->constrained('menorpreco_produtos')
                ->cascadeOnDelete();

            // Local (geohash) usado para atualizar os preços deste produto
            $table->string('local')->nullable();

            $table->timestamps();

            // Um produto só entra uma vez na lista de cada usuário
            $table->unique(['user_id', 'produto_id'], 'uniq_user_produto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lista_produtos');
    }
};
