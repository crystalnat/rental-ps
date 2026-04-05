<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TuyaCommand extends Model
{
    protected $fillable = ['store_id', 'device_id', 'switch', 'status', 'executed_at'];
    protected $casts    = ['switch' => 'boolean', 'executed_at' => 'datetime'];
}
