<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'client_id',
        'sub_user_id',
        'request_id',
        'based_cur',
        'base_currency',
        'convertion_cur',
        'converion_currency',
        'request_amount',
        'service_fee',
        'final_amount',
        'total_deduction',
        'return_amount',
        'convertion_rate',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'request_amount' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'total_deduction' => 'decimal:2',
        'return_amount' => 'decimal:2',
        'convertion_rate' => 'decimal:6',
        'approved_at' => 'datetime',
    ];

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
}
