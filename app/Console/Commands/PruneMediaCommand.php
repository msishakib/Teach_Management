<?php

namespace App\Console\Commands;

use App\Services\Media\MediaCleanupService;
use Illuminate\Console\Command;

class PruneMediaCommand extends Command
{
    protected $signature = 'media:prune';

    protected $description = 'Remove orphaned media files and stale upload chunks';

    public function handle(MediaCleanupService $cleanup): int
    {
        $result = $cleanup->run();

        $this->info("Orphaned media records removed: {$result['records']}");
        $this->info("Orphaned media files removed: {$result['files']}");
        $this->info("Stale upload sessions removed: {$result['chunks']}");

        return self::SUCCESS;
    }
}
