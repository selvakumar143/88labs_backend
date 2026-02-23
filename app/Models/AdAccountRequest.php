<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Client;

class AdAccountRequest extends Model
{
    protected $fillable = [
        'request_id',
        'client_id',
        'business_name', // 🔥 ADD THIS
        'platform',
        'timezone',
        'market_country',
        'currency',
        'business_manager_id',
        'website_url',
        'account_type',
        'personal_profile',
        'additional_notes',
        'number_of_accounts',
        'status',
        'approved_by',
        'approved_at'
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function clientProfileByUserId()
    {
        return $this->belongsTo(Client::class, 'client_id', 'user_id');
    }

    public function clientProfileByClientId()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
