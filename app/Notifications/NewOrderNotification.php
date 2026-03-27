<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewOrderNotification extends Notification
{
    use Queueable;

    private $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $type = $this->order->type === 'dine_in' ? 'Dine-In Meja ' . ($this->order->table?->name ?? '-') : 'Takeaway';
        return [
            'title' => 'Pesanan Baru!',
            'message' => "Ada pesanan baru #{$this->order->order_code} ({$type}) senilai Rp " . number_format($this->order->final_amount, 0, ',', '.'),
            'level' => 'info',
            'type' => 'new_order',
            'order_id' => $this->order->id,
            'link' => '/admin/orders',
        ];
    }
}
