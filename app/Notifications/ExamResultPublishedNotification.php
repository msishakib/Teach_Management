<?php

namespace App\Notifications;

use App\Models\ExamResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExamResultPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ExamResult $result,
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
        $this->result->loadMissing(['exam', 'student']);

        return (new MailMessage)
            ->subject('Exam result published')
            ->line("Results for {$this->result->exam?->name} have been published.")
            ->line('Student: '.$this->result->student?->name)
            ->line('GPA: '.$this->result->gpa.' ('.$this->result->letter_grade.')')
            ->line('Status: '.$this->result->status->getLabel());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'exam_id' => $this->result->exam_id,
            'exam_result_id' => $this->result->id,
            'student_id' => $this->result->student_id,
            'gpa' => $this->result->gpa,
            'status' => $this->result->status->value,
        ];
    }
}
