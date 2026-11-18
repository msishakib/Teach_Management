<?php

namespace App\Services\Media;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Optional ClamAV integration. The scanner is only invoked when the
 * "media.antivirus_enabled" setting (or config fallback) is on.
 */
class AntivirusService
{
    public function enabled(): bool
    {
        return (bool) setting('media.antivirus_enabled', config('media.antivirus.enabled', false));
    }

    public function binary(): string
    {
        return (string) setting('media.clamav_path', config('media.antivirus.binary', '/usr/bin/clamscan'));
    }

    /**
     * Scan an absolute file path. Throws when the file is infected or when the
     * scanner could not run.
     */
    public function scan(string $absolutePath): void
    {
        if (! $this->enabled()) {
            return;
        }

        if (! is_file($absolutePath)) {
            throw new RuntimeException("Cannot scan missing file [{$absolutePath}].");
        }

        $process = new Process([$this->binary(), '--no-summary', '--stdout', $absolutePath]);
        $process->setTimeout((int) config('media.antivirus.timeout', 60));
        $process->run();

        $exitCode = $process->getExitCode();

        if ($exitCode === 1) {
            throw new VirusDetectedException('The uploaded file failed the virus scan.');
        }

        if ($exitCode !== 0) {
            throw new RuntimeException('Antivirus scan failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }
}
