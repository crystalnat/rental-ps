<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DailyTargetReachedNotification extends Notification
{
    use Queueable;

    private $storeName;
    private $target;
    private $achievement;

    public function __construct(string $storeName, float $target, float $achievement)
    {
        $this->storeName = $storeName;
        $this->target = $target;
        $this->achievement = $achievement;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Target Tercapai! 🎉',
            'message' => "Toko '{$this->storeName}' telah mencapai target harian Rp " . number_format($this->target, 0, ',', '.') . " (Pencapaian: Rp " . number_format($this->achievement, 0, ',', '.') . ")",
            'level' => 'success',
            'type' => 'target_reached',
            'link' => '/admin/reports',
        ];
    }
}
