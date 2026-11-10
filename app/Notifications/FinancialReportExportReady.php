<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class FinancialReportExportReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $path,
        public string $reportType,
        public string $format,
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
        return (new MailMessage)
            ->subject('Financial report export ready')
            ->line("Your {$this->reportType} export ({$this->format}) is ready.")
            ->action('Download', $this->downloadUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'report_type' => $this->reportType,
            'format' => $this->format,
            'path' => $this->path,
            'download_url' => $this->downloadUrl(),
        ];
    }

    protected function downloadUrl(): string
    {
        return URL::temporarySignedRoute(
            'reports.export.download',
            now()->addDay(),
            ['path' => encrypt($this->path)],
        );
    }
}
