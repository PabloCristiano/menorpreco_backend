<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ColetaExecucao extends Model
{
    protected $table = 'coleta_execucoes';

    protected $fillable = [
        'gatilho',
        'status',
        'iniciado_em',
        'finalizado_em',
        'duracao_seg',
        'total_grupos',
        'total_chamadas_api',
        'total_processados',
        'total_erros',
        'mensagem_erro',
    ];

    protected $casts = [
        'iniciado_em'   => 'datetime',
        'finalizado_em' => 'datetime',
        'duracao_seg'   => 'decimal:2',
    ];

    public const STATUS_RODANDO = 'rodando';
    public const STATUS_SUCESSO = 'sucesso';
    public const STATUS_ERRO    = 'erro';
}
