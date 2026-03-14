<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class TopRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'client_id',
        'sub_user_id',
        'ad_account_request_id',
        'amount',
        'currency',
        'status',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function creatorUser()
    {
        return $this->belongsTo(User::class, 'sub_user_id');
    }

    public function adAccountRequest()
    {
        return $this->belongsTo(AdAccountRequest::class, 'ad_account_request_id');
    }
}
