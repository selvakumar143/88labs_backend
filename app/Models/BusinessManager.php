<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessManager extends Model
{
    protected $fillable = [
        'name',
        'mail',
        'contact',
        'status',
    ];
}
