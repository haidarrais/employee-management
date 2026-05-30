<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemConfig extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;

    protected $fillable = [
        'key',
        'value',
        'description',
    ];
}