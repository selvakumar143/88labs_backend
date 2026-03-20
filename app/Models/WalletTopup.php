<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Client;

class WalletTopup extends Model
{
    protected $appends = [
        'total_amount',
    ];

    protected $fillable = [
        'request_id',
        'client_id',
        'sub_user_id',
        'amount',
        'request_amount',
        'service_fee',
        'currency',
        'payment_mode',
        'transaction_hash',
        'status',
        'approved_by',
        'approved_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'request_amount' => 'decimal:2',
        'service_fee' => 'decimal:2',
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

    public function getTotalAmountAttribute(): string
    {
        $requestAmount = (float) ($this->request_amount ?? $this->amount ?? 0);
        $serviceFee = (float) ($this->service_fee ?? 0);

        return number_format($requestAmount + $serviceFee, 2, '.', '');
    }
}
