<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListaEstabelecimento extends Model
{
    protected $table = 'lista_estabelecimentos';

    protected $fillable = [
        'user_id',
        'estabelecimento_id',
        'apelido',
    ];

    public function estabelecimento()
    {
        return $this->belongsTo(MenorprecoEstabelecimento::class, 'estabelecimento_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
