<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'clientName',
        'country',
        'email',
        'phone',
        'clientType',
        'niche',
        'marketCountry',
        'settlementMode',
        'statementCycle',
        'settlementCurrency',
        'cooperationStart',
        'serviceFeePercent',
        'serviceFeeEffectiveTime',
        'enabled',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
