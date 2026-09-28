<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Services\BackupService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BackupSafetyTest extends TestCase
{
    public function test_database_backup_restores_rows_and_ignores_columns_not_in_the_current_schema(): void
    {
        $package = Package::create([
            'name' => 'Backup package',
            'slug' => 'backup-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Original description',
        ]);
        $backupService = app(BackupService::class);
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);

        try {
            $backup = json_decode(file_get_contents($backupPath), true, 512, JSON_THROW_ON_ERROR);
            $backup['tables']['packages'][0]['future_column'] = 'ignored during restore';
            file_put_contents($backupPath, json_encode($backup, JSON_THROW_ON_ERROR), LOCK_EX);
            $package->update(['description' => 'Changed description']);

            $restoredRows = $backupService->restore($backupName);

            $this->assertGreaterThan(0, $restoredRows);
            $this->assertSame('Original description', $package->fresh()->description);
        } finally {
            $backupService->delete($backupName);
        }
    }

    public function test_restore_requires_a_correct_admin_password(): void
    {
        $response = $this->from('/admin/backups')->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_email' => env('ADMIN_EMAIL', 'admin@3yos.com'),
        ])->post(route('admin.backups.restore'), [
            'backup' => 'backup-20260926000000.json',
            'password_confirmation' => 'incorrect-password',
        ]);

        $response->assertRedirect('/admin/backups');
        $response->assertSessionHasErrors('password_confirmation');
    }

    public function test_backup_deletion_requires_a_correct_admin_password(): void
    {
        $response = $this->from('/admin/backups')->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_email' => env('ADMIN_EMAIL', 'admin@3yos.com'),
        ])->delete(route('admin.backups.delete'), [
            'backup' => 'backup-20260926000000.json',
            'password_confirmation' => 'incorrect-password',
        ]);

        $response->assertRedirect('/admin/backups');
        $response->assertSessionHasErrors('password_confirmation');
    }

    public function test_admin_can_upload_a_valid_backup_file(): void
    {
        $backupService = app(BackupService::class);
        $sourceBackupPath = $backupService->create();

        try {
            $upload = UploadedFile::fake()->createWithContent(
                'uploaded-backup-20260929020000.json',
                file_get_contents($sourceBackupPath),
                'application/json'
            );

            $response = $this->from('/admin/backups')->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_email' => env('ADMIN_EMAIL', 'admin@3yos.com'),
            ])->post(route('admin.backups.upload'), [
                'backup_file' => $upload,
            ]);

            $response->assertRedirect('/admin/backups');
            $response->assertSessionHas('success', 'Backup uploaded successfully.');
            $this->assertNotEmpty(array_filter($backupService->listBackups(), fn (string $backup) => str_starts_with($backup, 'uploaded-backup-')));
        } finally {
            foreach ($backupService->listBackups() as $backup) {
                if (str_starts_with($backup, 'uploaded-backup-')) {
                    $backupService->delete($backup);
                }
            }
            $backupService->delete(basename($sourceBackupPath));
        }
    }

    public function test_invalid_backup_file_upload_is_rejected(): void
    {
        $response = $this->from('/admin/backups')->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_email' => env('ADMIN_EMAIL', 'admin@3yos.com'),
        ])->post(route('admin.backups.upload'), [
            'backup_file' => UploadedFile::fake()->create('invalid.txt', 10, 'text/plain'),
        ]);

        $response->assertRedirect('/admin/backups');
        $response->assertSessionHasErrors('backup_file');
    }
}