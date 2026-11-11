<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class FeeDueReminderCommand extends Command
{
    protected $signature = 'notify:fee-due';
    protected $description = 'Send fee due reminders to students with outstanding invoices.';

    public function __construct(protected NotificationService $notifications)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Fetch students with due invoices and send reminders - simplified stub
        $this->info('Dispatching fee due reminders (stub)');
        // Implementation: query StudentInvoice where due > 0 and notify student user
        return 0;
    }
}

