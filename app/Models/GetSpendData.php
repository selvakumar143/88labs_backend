<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GetSpendData extends Model
{
    protected $table = 'get_spend_data';

    protected $fillable = [
        'client_id',
        'account_id',
        'spend',
        'date_start',
        'date_stop',
    ];

    protected $casts = [
        'spend' => 'decimal:2',
        'date_start' => 'date',
        'date_stop' => 'date',
    ];
}
