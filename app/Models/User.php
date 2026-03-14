<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Client;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'client_id',
        'created_by',
        'name',
        'email',
        'password',
        'email_verified_at',
        'status'
    ];
    
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function client()
    {
        return $this->hasOne(Client::class, 'primary_admin_user_id');
    }

    public function tenantClient()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function tenantClientId(): ?int
    {
        if (!empty($this->client_id)) {
            return (int) $this->client_id;
        }

        $ownedClientId = optional($this->client)->id;
        return $ownedClientId ? (int) $ownedClientId : null;
    }

    public function tenantOwnerUserId(): int
    {
        $tenantClient = $this->tenantClient;
        if ($tenantClient) {
            if (!empty($tenantClient->primary_admin_user_id)) {
                return (int) $tenantClient->primary_admin_user_id;
            }
        }

        return (int) $this->id;
    }
}
