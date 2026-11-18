<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CourseOrderReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ((bool) setting('notification.email_enabled', true) && filled($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->order->loadMissing(['course', 'receipt']);

        $mail = (new MailMessage)
            ->subject('Course purchase receipt '.$this->order->order_no)
            ->line('Thank you for your purchase.')
            ->line('Course: '.$this->order->course?->name)
            ->line('Order: '.$this->order->order_no)
            ->line('Amount: '.(string) setting('general.currency_symbol', '৳').number_format((float) $this->order->net_amount, 2));

        if ($this->order->receipt_id) {
            $mail->action('Download receipt', url('/receipts/'.$this->order->receipt_id.'/pdf'));
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_no' => $this->order->order_no,
            'course_id' => $this->order->course_id,
            'receipt_id' => $this->order->receipt_id,
            'net_amount' => $this->order->net_amount,
        ];
    }

}

