<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class WalletTopup extends Model
{
    protected $appends = [
        'total_amount',
    ];

    protected $fillable = [
        'request_id',
        'client_id',
        'amount',
        'currency',
        'payment_mode',
        'transaction_hash',
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

    public function getTotalAmountAttribute(): string
    {
        return (string) $this->amount;
    }
}
