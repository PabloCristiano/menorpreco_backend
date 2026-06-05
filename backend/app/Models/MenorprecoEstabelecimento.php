<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenorprecoEstabelecimento extends Model
{
    use HasFactory;

    protected $table = 'menorpreco_estabelecimentos';

    protected $fillable = [
        'codigo',
        'nome_fantasia',
        'razao_social',
        'bairro',
        'cidade',
        'uf',
        'tp_logr',
        'nm_logr',
        'nr_logr',
    ];

    /**
     * Um estabelecimento possui vários históricos de preço
     */
    public function historicoPrecos()
    {
        return $this->hasMany(
            MenorprecoHistoricoPreco::class,
            'estabelecimento_id'
        );
    }
}
