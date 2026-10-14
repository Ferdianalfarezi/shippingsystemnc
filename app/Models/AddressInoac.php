<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AddressInoac extends Model
{
    protected $table = 'addressinoac';

    protected $fillable = [
        'part_no',
        'inoac_code',
        'customer_code',
        'part_name',
        'rack_no',
    ];
}
