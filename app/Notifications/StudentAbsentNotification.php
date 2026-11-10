<?php

namespace App\Notifications;

use App\Models\AttendanceSession;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentAbsentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Student $student,
        public AttendanceSession $session,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ((bool) setting('notification.email_enabled', false) && filled($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Absence notice: '.$this->student->name)
            ->line("{$this->student->name} ({$this->student->admission_no}) was marked absent.")
            ->line('Date: '.$this->session->date->toDateString())
            ->line('Batch: '.($this->session->batch?->name ?? 'N/A'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'student_id' => $this->student->id,
            'student_name' => $this->student->name,
            'admission_no' => $this->student->admission_no,
            'session_id' => $this->session->id,
            'date' => $this->session->date->toDateString(),
            'batch_id' => $this->session->batch_id,
            'message' => "{$this->student->name} was marked absent on {$this->session->date->toDateString()}.",
        ];
    }
}
