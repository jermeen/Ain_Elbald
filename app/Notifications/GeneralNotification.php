<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GeneralNotification extends Notification
{
    use Queueable;

    protected $details;

    /**
     * بنستقبل البيانات هنا لما نبعت الإشعار
     */
    public function __construct($details)
    {
        $this->details = $details;
    }

    /**
     * بنقول للارافيل خزن الإشعار في جدول الـ Database
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * دي البيانات اللي هتتسجل في عمود الـ data في الداتابيز
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title'     => $this->details['title'] ?? 'New Notification',
            'description' => $this->details['description'] ?? '',
            'message'   => $this->details['message'] ?? '',
            'report_id' => $this->details['report_id'] ?? null,
            'status'    => $this->details['status'] ?? 'New',
            'photo'     => $this->details['photo'] ?? null,
        ];
    }
}