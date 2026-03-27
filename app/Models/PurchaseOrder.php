<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'store_id',
        'supplier_id',
        'created_by',
        'po_number',
        'status',
        'total_amount',
        'notes',
        'expected_date',
        'received_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'expected_date' => 'date',
        'received_at' => 'datetime',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
