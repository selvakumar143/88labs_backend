<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientService extends Model
{
    protected $fillable = ['client_id', 'services'];

    protected $casts = [
        'services' => 'array',
    ];
}
