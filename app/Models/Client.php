<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Client extends Model
{
    protected $fillable = [
        'clientCode',
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
        'primary_admin_user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function primaryAdmin()
    {
        return $this->belongsTo(User::class, 'primary_admin_user_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'client_id');
    }
}
