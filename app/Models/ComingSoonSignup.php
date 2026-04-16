<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComingSoonSignup extends Model
{
    protected $fillable = [
        'email',
        'country',
        'city',
        'ip_address',
    ];
}
