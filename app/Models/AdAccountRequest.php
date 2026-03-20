<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Client;
use App\Models\BusinessManager;

class AdAccountRequest extends Model
{
    protected $fillable = [
        'request_id',
        'client_id',
        'sub_user_id',
        'business_name', // 🔥 ADD THIS
        'platform',
        'timezone',
        'market_country',
        'currency',
        'vcc_provider',
        'business_manager_id',
        'account_name',
        'account_preference',
        'account_id',
        'card_type',
        'card_number',
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
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function creatorUser()
    {
        return $this->belongsTo(User::class, 'sub_user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function businessManager()
    {
        return $this->belongsTo(BusinessManager::class, 'business_manager_id');
    }
}
