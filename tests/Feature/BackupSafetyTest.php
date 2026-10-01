<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
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
            $this->assertStringContainsString('/storage/app/private/backups/', str_replace('\\', '/', $backupPath));
            $encrypted = file_get_contents($backupPath);
            $this->assertNotFalse($encrypted);
            $this->assertStringNotContainsString('"packages"', $encrypted);
            $backup = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
            $backup['tables']['packages'][0]['future_column'] = 'ignored during restore';
            file_put_contents($backupPath, Crypt::encryptString(json_encode($backup, JSON_THROW_ON_ERROR)), LOCK_EX);
            $package->update(['description' => 'Changed description']);

            $restoredRows = $backupService->restore($backupName);

            $this->assertGreaterThan(0, $restoredRows);
            $this->assertSame('Original description', $package->fresh()->description);
        } finally {
            $backupService->delete($backupName);
        }
    }

    public function test_tampered_encrypted_backup_is_rejected_without_restoring_rows(): void
    {
        $package = Package::create([
            'name' => 'Tamper package',
            'slug' => 'tamper-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Keep this description',
        ]);
        $backupService = app(BackupService::class);
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);

        try {
            file_put_contents($backupPath, file_get_contents($backupPath).'tampered', LOCK_EX);
            try {
                $backupService->restore($backupName);
                $this->fail('A tampered backup must not be restored.');
            } catch (\InvalidArgumentException) {
                // Expected: the authenticated ciphertext was modified.
            }
            $this->assertSame('Keep this description', $package->fresh()->description);
        } finally {
            $backupService->delete($backupName);
        }
    }

    public function test_legacy_plain_json_backup_remains_restorable(): void
    {
        $package = Package::create([
            'name' => 'Legacy package',
            'slug' => 'legacy-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Original legacy description',
        ]);
        $backupService = app(BackupService::class);
        $encryptedPath = $backupService->create();
        $backup = json_decode(Crypt::decryptString(file_get_contents($encryptedPath)), true, 512, JSON_THROW_ON_ERROR);
        unset($backup['backup_version']);
        $legacyName = 'backup-'.now()->format('YmdHis').'-'.random_int(100, 999).'.json';
        $legacyDirectory = storage_path('app/backups');
        if (! is_dir($legacyDirectory)) {
            mkdir($legacyDirectory, 0700, true);
        }
        $legacyPath = $legacyDirectory.DIRECTORY_SEPARATOR.$legacyName;

        try {
            file_put_contents($legacyPath, json_encode($backup, JSON_THROW_ON_ERROR), LOCK_EX);
            $package->update(['description' => 'Changed after legacy backup']);

            $this->assertFalse($backupService->isEncrypted($legacyName));
            $this->assertGreaterThan(0, $backupService->restore($legacyName));
            $this->assertSame('Original legacy description', $package->fresh()->description);
        } finally {
            @unlink($legacyPath);
            $backupService->delete(basename($encryptedPath));
        }
    }

    public function test_restore_requires_a_correct_admin_password(): void
    {
        $admin = User::factory()->create(['role' => 'full', 'password' => 'correct-password']);
        $response = $this->from('/admin/backups')->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_user_id' => $admin->id,
            'admin_email' => $admin->email,
        ])->post(route('admin.backups.restore'), [
            'backup' => 'backup-20260926000000.json',
            'confirm_legacy' => '1',
            'current_admin_password' => 'incorrect-password',
        ]);

        $response->assertRedirect('/admin/backups');
        $response->assertSessionHasErrors('current_admin_password');
    }

    public function test_legacy_restore_requires_explicit_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'full', 'password' => 'correct-password']);
        $response = $this->from('/admin/backups')->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_user_id' => $admin->id,
            'admin_email' => $admin->email,
        ])->post(route('admin.backups.restore'), [
            'backup' => 'backup-20260926000000.json',
            'current_admin_password' => 'correct-password',
        ]);

        $response->assertRedirect('/admin/backups');
        $response->assertSessionHasErrors('backup');
    }

    public function test_backup_download_requires_admin_authentication(): void
    {
        $response = $this->post(route('admin.backups.download'), [
            'backup' => 'backup-20260926000000.json.enc',
        ]);

        $response->assertRedirect();
    }

    public function test_admin_downloads_encrypted_backup_without_decrypting_it(): void
    {
        $backupService = app(BackupService::class);
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $ciphertext = file_get_contents($backupPath);

        try {
            $response = $this->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_email' => 'backup-admin@example.test',
            ])->post(route('admin.backups.download'), ['backup' => $backupName]);

            $response->assertDownload($backupName);
            $this->assertSame($ciphertext, file_get_contents($backupPath));
        } finally {
            $backupService->delete($backupName);
        }
    }

    public function test_backup_deletion_requires_a_correct_admin_password(): void
    {
        $admin = User::factory()->create(['role' => 'full', 'password' => 'correct-password']);
        $response = $this->from('/admin/backups')->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_user_id' => $admin->id,
            'admin_email' => $admin->email,
        ])->delete(route('admin.backups.delete'), [
            'backup' => 'backup-20260926000000.json',
            'current_admin_password' => 'incorrect-password',
        ]);

        $response->assertRedirect('/admin/backups');
        $response->assertSessionHasErrors('current_admin_password');
    }

    public function test_admin_can_upload_a_valid_backup_file(): void
    {
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $sourceBackupPath = $backupService->create();

        try {
            $upload = UploadedFile::fake()->createWithContent(
                'uploaded-backup-20260929020000.json',
                Crypt::decryptString(file_get_contents($sourceBackupPath)),
                'application/json'
            );

            $response = $this->from('/admin/backups')->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_email' => 'backup-admin@example.test',
            ])->post(route('admin.backups.upload'), [
                'backup_file' => $upload,
            ]);

            $response->assertRedirect('/admin/backups');
            $response->assertSessionHas('success', 'Backup uploaded successfully.');
            $uploadedBackups = array_values(array_filter($backupService->listBackups(), fn (string $backup) => str_starts_with($backup, 'uploaded-backup-')));
            $this->assertNotEmpty($uploadedBackups);
            foreach ($uploadedBackups as $uploadedBackup) {
                $this->assertTrue($backupService->isEncrypted($uploadedBackup));
            }
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
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
            'admin_email' => 'backup-admin@example.test',
        ])->post(route('admin.backups.upload'), [
            'backup_file' => UploadedFile::fake()->create('invalid.txt', 10, 'text/plain'),
        ]);

        $response->assertRedirect('/admin/backups');
        $response->assertSessionHasErrors('backup_file');
    }
}