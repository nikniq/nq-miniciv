<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MiniCivState extends Model
{
    protected $table = 'miniciv_states';

    protected $fillable = [
        'user_id', 'state',
    ];

    protected $casts = [
        'state' => 'array',
    ];
}
