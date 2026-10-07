<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Services\BackupService;
use Illuminate\Console\Scheduling\Schedule;
use Mockery;
use Tests\TestCase;

class ScheduledBackupTest extends TestCase
{
    public function test_scheduled_backup_runs_once_daily_at_2359_in_the_application_timezone_without_overlapping(): void
    {
        config(['app.timezone' => 'Asia/Manila']);

        $events = app(Schedule::class)->events();
        $backupEvents = collect($events)->filter(
            fn ($event) => str_contains($event->command, 'backups:auto-create')
        );

        $this->assertCount(1, $backupEvents);
        $event = $backupEvents->first();
        $this->assertSame('59 23 * * *', $event->expression);
        $this->assertSame('Asia/Manila', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_scheduled_backup_command_uses_existing_backup_service_and_records_system_activity(): void
    {
        $backupService = Mockery::mock(BackupService::class);
        $backupService->shouldReceive('databaseStatus')
            ->once()
            ->andReturn(['status' => 'healthy']);
        $backupService->shouldReceive('create')
            ->once()
            ->andReturn(storage_path('app/private/backups/backup-scheduled.json.enc'));
        $backupService->shouldReceive('currentFormat')
            ->once()
            ->andReturn(['name' => 'JSON v2']);
        $this->app->instance(BackupService::class, $backupService);

        $this->artisan('backups:auto-create')
            ->expectsOutput('Created scheduled encrypted backup backup-scheduled.json.enc.')
            ->assertSuccessful();

        $this->assertDatabaseHas('activity_logs', [
            'actor_name' => 'System',
            'actor_role' => 'system',
            'action' => 'Backup created',
            'method' => 'SCHEDULE',
            'description' => 'Created scheduled encrypted JSON v2 backup backup-scheduled.json.enc.',
        ]);
        $this->assertSame(1, ActivityLog::where('action', 'Backup created')->count());
    }

    public function test_scheduled_backup_does_not_run_when_database_is_unhealthy(): void
    {
        $backupService = Mockery::mock(BackupService::class);
        $backupService->shouldReceive('databaseStatus')
            ->once()
            ->andReturn(['status' => 'recovery_required']);
        $backupService->shouldNotReceive('create');
        $this->app->instance(BackupService::class, $backupService);

        $this->artisan('backups:auto-create')
            ->expectsOutput('Scheduled backup skipped because the database health is recovery_required.')
            ->assertFailed();
    }
}
