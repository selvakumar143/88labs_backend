<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountManagement extends Model
{
    protected $table = 'account_management';

    protected $fillable = [
        'client_id',
        'name',
        'account_id',
        'client_name',
        'platform',
        'currency',
        'account_created_at',
        'status',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
