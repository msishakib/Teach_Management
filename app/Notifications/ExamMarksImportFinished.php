<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ExamMarksImportFinished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $examSubjectId,
        public int $imported,
        public int $failed,
        public ?string $errorReportPath,
        public string $disk = 'local',
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Exam marks import finished')
            ->line("Imported {$this->imported} row(s) for exam subject #{$this->examSubjectId}.")
            ->line("Failed rows: {$this->failed}.");

        if ($this->errorReportPath && Storage::disk($this->disk)->exists($this->errorReportPath)) {
            $mail->action(
                'Download error report',
                URL::temporarySignedRoute(
                    'exams.marks.import-errors',
                    now()->addDay(),
                    ['path' => $this->errorReportPath, 'disk' => $this->disk],
                ),
            );
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'exam_subject_id' => $this->examSubjectId,
            'imported' => $this->imported,
            'failed' => $this->failed,
            'error_report_path' => $this->errorReportPath,
            'disk' => $this->disk,
        ];
    }
}
