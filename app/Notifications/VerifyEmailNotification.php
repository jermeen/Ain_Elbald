<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    // تعريف المتغيرات اللي هنستقبلها
    private $code;
    private $type;

    /**
     * [تعديل]: نمرر الكود ونوع العملية (تفعيل حساب أم نسيان كلمة سر)
     */
    public function __construct($code, $type = 'verify')
    {
        $this->code = $code;
        $this->type = $type;
    }

    /**
     * تحديد وسيلة الإرسال (الإيميل)
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * [تعديل]: تصميم شكل الإيميل اللي هيوصل لليوزر
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->type === 'verify' ? 'تفعيل حسابك' : 'إعادة تعيين كلمة المرور';
        $message = $this->type === 'verify' 
            ? 'شكراً لتسجيلك في تطبيقنا. استخدم الكود التالي لتفعيل حسابك:' 
            : 'لقد طلبت إعادة تعيين كلمة المرور. استخدم الكود التالي لإتمام العملية:';

        return (new MailMessage)
                    ->subject($subject) // عنوان الرسالة
                    ->greeting('أهلاً بك!') // التحية
                    ->line($message)    // نص الرسالة
                    ->line('** ' . $this->code . ' **') // عرض الكود بشكل واضح
                    ->line('هذا الكود صالح لمدة ساعة واحدة فقط.')
                    ->line('شكراً لاستخدامك تطبيقنا!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}