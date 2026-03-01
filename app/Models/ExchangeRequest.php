<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRequest extends Model
{
    protected $fillable = [
        'client_id',
        'based_cur',
        'convertion_cur',
        'request_amount',
        'service_fee',
        'final_amount',
        'status',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
