<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateScheduledBackup extends Command
{
    protected $signature = 'backups:auto-create';

    protected $description = 'Create the daily encrypted application backup';

    public function handle(BackupService $backupService): int
    {
        $databaseStatus = $backupService->databaseStatus();
        if ($databaseStatus['status'] !== 'healthy') {
            $message = 'Scheduled backup skipped because the database health is '.$databaseStatus['status'].'.';
            $this->error($message);
            Log::warning($message);

            return self::FAILURE;
        }

        try {
            $path = $backupService->create();
        } catch (Throwable $exception) {
            report($exception);
            $this->recordActivity(
                'Backup creation failed',
                'The scheduled encrypted database backup could not be created.'
            );
            $this->error('The scheduled database backup could not be created.');

            return self::FAILURE;
        }

        $filename = basename($path);
        $format = $backupService->currentFormat()['name'];
        $this->recordActivity(
            'Backup created',
            'Created scheduled encrypted '.$format.' backup '.$filename.'.'
        );
        $this->info('Created scheduled encrypted backup '.$filename.'.');

        return self::SUCCESS;
    }

    private function recordActivity(string $action, string $description): void
    {
        $timestamp = now(config('app.timezone'));

        ActivityLog::create([
            'user_id' => null,
            'actor_name' => 'System',
            'actor_role' => 'system',
            'action' => $action,
            'method' => 'SCHEDULE',
            'activity_date' => $timestamp->toDateString(),
            'activity_time' => $timestamp->toTimeString(),
            'description' => $description,
        ]);
    }
}
