<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenorprecoHistoricoPreco extends Model
{
    use HasFactory;

    protected $table = 'menorpreco_historico_precos';

    protected $fillable = [
        'produto_id',
        'estabelecimento_id',
        'preco',
        'preco_tabela',
        'desconto',
        'distancia_km',
        'datahora_nota',
        'data_coleta',
    ];

    protected $casts = [
        'preco'           => 'decimal:2',
        'preco_tabela'    => 'decimal:2',
        'desconto'        => 'decimal:2',
        'distancia_km'    => 'decimal:2',
        'datahora_nota'   => 'datetime',
        'data_coleta'     => 'date',
    ];

    /**
     * Histórico pertence a um produto
     */
    public function produto()
    {
        return $this->belongsTo(
            MenorprecoProduto::class,
            'produto_id'
        );
    }

    /**
     * Histórico pertence a um estabelecimento
     */
    public function estabelecimento()
    {
        return $this->belongsTo(
            MenorprecoEstabelecimento::class,
            'estabelecimento_id'
        );
    }
}
