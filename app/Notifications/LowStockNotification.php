<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    use Queueable;

    private $productName;
    private $storeName;
    private $currentStock;
    private $unit;

    public function __construct(string $productName, string $storeName, float $currentStock, string $unit)
    {
        $this->productName = $productName;
        $this->storeName = $storeName;
        $this->currentStock = $currentStock;
        $this->unit = $unit;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Stok Menipis!',
            'message' => "Stok produk '{$this->productName}' di '{$this->storeName}' sisa {$this->currentStock} {$this->unit}.",
            'level' => 'warning',
            'type' => 'low_stock',
            'link' => '/admin/inventory',
        ];
    }
}
