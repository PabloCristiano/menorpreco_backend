<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenorprecoProduto extends Model
{
    use HasFactory;

    protected $table = 'menorpreco_produtos';

    protected $fillable = [
        'gtin',
        'palavrachave',
        'descricao',
        'ncm',
        'volume',
        'unidade',
        'categoria',
        'local',
    ];

    /**
     * Um produto possui vários históricos de preço
     */
    public function historicoPrecos()
    {
        return $this->hasMany(
            MenorprecoHistoricoPreco::class,
            'produto_id'
        );
    }
}
