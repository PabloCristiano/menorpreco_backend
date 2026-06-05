<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListaProduto extends Model
{
    protected $table = 'lista_produtos';

    protected $fillable = [
        'user_id',
        'produto_id',
        'local',
    ];

    public function produto()
    {
        return $this->belongsTo(MenorprecoProduto::class, 'produto_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
