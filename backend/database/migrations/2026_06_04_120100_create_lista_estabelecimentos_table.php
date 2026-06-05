<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lista_estabelecimentos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('estabelecimento_id')
                ->constrained('menorpreco_estabelecimentos')
                ->cascadeOnDelete();

            // Apelido dado pelo usuário (ex.: "Mercado perto de casa")
            $table->string('apelido')->nullable();

            $table->timestamps();

            // Um estabelecimento só entra uma vez na lista de cada usuário
            $table->unique(['user_id', 'estabelecimento_id'], 'uniq_user_estabelecimento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lista_estabelecimentos');
    }
};
