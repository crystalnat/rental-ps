<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFeedback extends Model
{
    protected $table = 'order_feedbacks';

    protected $fillable = [
        'order_id',
        'rating',
        'comment',
        'source',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
