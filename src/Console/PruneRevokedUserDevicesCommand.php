<?php

declare(strict_types=1);

namespace KirchDev\DeviceSessions\Console;

use Illuminate\Console\Command;
use KirchDev\DeviceSessions\Support\DeviceSessions;

class PruneRevokedUserDevicesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'device-sessions:prune {--days= : Retention period in days for revoked devices (defaults to config)}';

    /**
     * @var string
     */
    protected $description = 'Delete user devices revoked longer than the retention window (remember tokens cascade).';

    public function handle(): int
    {
        $option = $this->option('days');
        $days = max(1, is_numeric($option) ? (int) $option : $this->configuredRetentionDays());
        $cutoff = now()->subDays($days);

        $deviceModel = DeviceSessions::deviceModel();

        $deleted = $deviceModel::query()
            ->whereNotNull('revoked_at')
            ->where('revoked_at', '<=', $cutoff)
            ->delete();

        $this->info(sprintf('Pruned %d revoked devices older than %d days.', $deleted, $days));

        return self::SUCCESS;
    }

    private function configuredRetentionDays(): int
    {
        $days = config('device-sessions.prune.retention_days', 180);

        return is_numeric($days) ? (int) $days : 180;
    }
}
