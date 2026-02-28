<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountManagement extends Model
{
    protected $table = 'account_management';

    protected $fillable = [
        'client_id',
        'business_manager_id',
        'name',
        'account_id',
        'card_type',
        'card_number',
        'platform',
        'currency',
        'account_created_at',
        'status',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function businessManager()
    {
        return $this->belongsTo(BusinessManager::class);
    }
}
