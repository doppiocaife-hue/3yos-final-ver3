<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\BackupService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\QueryException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BackupSafetyTest extends TestCase
{
    public function test_backup_creation_timestamp_uses_application_timezone_and_drives_latest_order(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2036-10-01T23:59:10.123456+08:00'));
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $firstPath = $backupService->create();

        try {
            $firstName = basename($firstPath);
            $firstPayload = json_decode(Crypt::decryptString(file_get_contents($firstPath)), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('2036-10-01T23:59:10.123456+08:00', $firstPayload['created_at']);

            $firstMetadata = $backupService->inspect($firstName);
            $this->assertSame('Asia/Manila', $firstMetadata['created_at']->timezoneName);
            $this->assertSame('October 1, 2036 at 11:59 PM', $firstMetadata['created_at']->format('F j, Y \a\t g:i A'));

            Carbon::setTestNow(Carbon::parse('2036-10-02T00:00:10.654321+08:00'));
            $secondPath = $backupService->create();
            $secondName = basename($secondPath);
            $secondPayload = json_decode(Crypt::decryptString(file_get_contents($secondPath)), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('2036-10-02T00:00:10.654321+08:00', $secondPayload['created_at']);

            $response = $this->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_email' => 'backup-time-admin@example.test',
            ])->get(route('admin.backups'))
                ->assertOk();
            $byName = collect($response->viewData('backups'))->keyBy('name');
            $this->assertTrue($byName[$secondName]['latest'], json_encode($byName->only([$firstName, $secondName])->toArray()));
            $this->assertFalse($byName[$secondName]['older']);
            $this->assertFalse($byName[$firstName]['latest']);
            $this->assertTrue($byName[$firstName]['older']);
            $response->assertSee('Created: October 1, 2036 at 11:59 PM')
                ->assertSee('Created: October 2, 2036 at 12:00 AM');
        } finally {
            Carbon::setTestNow();
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

    public function test_missing_or_timezone_less_creation_timestamp_is_not_replaced_by_file_time(): void
    {
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $contents = json_decode(Crypt::decryptString(file_get_contents($backupPath)), true, 512, JSON_THROW_ON_ERROR);
        $contents['backup_version'] = 1;
        unset($contents['created_at']);
        file_put_contents($backupPath, Crypt::encryptString(json_encode($contents, JSON_THROW_ON_ERROR)), LOCK_EX);
        touch($backupPath, now()->addYears(10)->getTimestamp());

        try {
            $metadata = $backupService->inspect($backupName);
            $this->assertTrue($metadata['compatible']);
            $this->assertNull($metadata['created_at']);
            $this->assertNull($metadata['sort_timestamp']);

            $contents['created_at'] = '2026-10-01T23:59:00';
            file_put_contents($backupPath, Crypt::encryptString(json_encode($contents, JSON_THROW_ON_ERROR)), LOCK_EX);
            $this->assertNull($backupService->inspect($backupName)['created_at']);
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

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
            $this->assertStringNotContainsString('backup_version', $encrypted);
            $this->assertStringNotContainsString('backup_format', $encrypted);
            $this->assertStringNotContainsString('APP_KEY', $encrypted);
            $backup = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(BackupService::BACKUP_FORMAT, $backup['backup_format']);
            $this->assertSame(BackupService::BACKUP_VERSION, $backup['backup_version']);
            $this->assertArrayNotHasKey('users', $backup['tables']);
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

    public function test_wrong_encryption_key_fails_safely_before_any_restore_changes(): void
    {
        $package = Package::create([
            'name' => 'Wrong key package',
            'slug' => 'wrong-key-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Current data must remain intact',
        ]);
        $admin = User::factory()->create([
            'role' => 'full',
            'password' => 'CurrentAdmin#2026',
            'is_active' => true,
        ]);
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $package->update(['description' => 'Changed after backup']);
        $originalEncrypter = Crypt::getFacadeRoot();
        $backupsBeforeRestore = $backupService->listBackups();

        try {
            Crypt::swap(new Encrypter(random_bytes(32), 'AES-256-CBC'));

            $response = $this->from('/admin/backups')->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_user_id' => $admin->id,
                'admin_email' => $admin->email,
                'admin_session_version' => $admin->session_version,
            ])->post(route('admin.backups.restore'), [
                'backup' => $backupName,
                'current_admin_password' => 'CurrentAdmin#2026',
            ]);

            $response->assertRedirect('/admin/backups')
                ->assertSessionHasErrors([
                    'backup' => 'This backup could not be verified as compatible. It may be corrupted or modified. No data was restored.',
                ])
                ->assertDontSee('CurrentAdmin#2026')
                ->assertDontSee('AES-256-CBC');
            $this->assertSame('Changed after backup', $package->fresh()->description);
            $this->assertSame($backupsBeforeRestore, $backupService->listBackups());
            $this->assertTrue(Hash::check('CurrentAdmin#2026', $admin->fresh()->password));
        } finally {
            Crypt::swap($originalEncrypter);
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

    public function test_tampered_backup_restore_returns_generic_error_without_creating_safety_backup(): void
    {
        $package = Package::create([
            'name' => 'Tampered route package',
            'slug' => 'tampered-route-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Current data must remain intact',
        ]);
        $admin = User::factory()->create([
            'role' => 'full',
            'password' => 'CurrentAdmin#2026',
            'is_active' => true,
        ]);
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        file_put_contents($backupPath, file_get_contents($backupPath).'tampered', LOCK_EX);
        $backupsBeforeRestore = $backupService->listBackups();
        $package->update(['description' => 'Changed after backup']);

        try {
            $response = $this->from('/admin/backups')->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_user_id' => $admin->id,
                'admin_email' => $admin->email,
                'admin_session_version' => $admin->session_version,
            ])->post(route('admin.backups.restore'), [
                'backup' => $backupName,
                'current_admin_password' => 'CurrentAdmin#2026',
            ]);

            $response->assertRedirect('/admin/backups')
                ->assertSessionHasErrors([
                    'backup' => 'This backup could not be verified as compatible. It may be corrupted or modified. No data was restored.',
                ])
                ->assertDontSee('CurrentAdmin#2026');
            $this->assertSame('Changed after backup', $package->fresh()->description);
            $this->assertSame($backupsBeforeRestore, $backupService->listBackups());
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
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
        unset($backup['backup_format']);
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
            $legacyMetadata = $backupService->inspect($legacyName);
            $this->assertSame('Legacy format', $legacyMetadata['format']);
            $this->assertTrue($legacyMetadata['legacy']);
            $this->assertTrue($legacyMetadata['compatible']);
            $this->assertGreaterThan(0, $backupService->restore($legacyName));
            $this->assertSame('Original legacy description', $package->fresh()->description);
        } finally {
            @unlink($legacyPath);
            $backupService->delete(basename($encryptedPath));
        }
    }

    public function test_backup_page_displays_the_current_format_and_system_compatibility(): void
    {
        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_email' => 'backup-admin@example.test',
        ])->get(route('admin.backups'));

        $response->assertOk()
            ->assertSee('Backup format:')
            ->assertSee('JSON v'.BackupService::BACKUP_VERSION)
            ->assertDontSee(BackupService::BACKUP_FORMAT)
            ->assertSee('Current system format')
            ->assertSee('backup-page-controls', false)
            ->assertSee('backup-format-indicator', false)
            ->assertDontSee('backup-format-card', false);
    }

    public function test_backup_list_uses_each_payload_version_and_creation_time_for_status_and_latest_badge(): void
    {
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $firstPath = $backupService->create();
        $secondPath = $backupService->create();
        $firstName = basename($firstPath);
        $secondName = basename($secondPath);

        try {
            $firstContents = json_decode(Crypt::decryptString(file_get_contents($firstPath)), true, 512, JSON_THROW_ON_ERROR);
            $secondContents = json_decode(Crypt::decryptString(file_get_contents($secondPath)), true, 512, JSON_THROW_ON_ERROR);

            $firstContents['created_at'] = now()->addHour()->toIso8601String();
            $secondContents['backup_version'] = 0;
            $secondContents['created_at'] = now()->subDay()->toIso8601String();
            file_put_contents($firstPath, Crypt::encryptString(json_encode($firstContents, JSON_THROW_ON_ERROR)), LOCK_EX);
            file_put_contents($secondPath, Crypt::encryptString(json_encode($secondContents, JSON_THROW_ON_ERROR)), LOCK_EX);

            $response = $this->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_email' => 'backup-admin@example.test',
            ])->get(route('admin.backups'));

            $response->assertOk()->assertViewHas('backups', function (array $backups) use ($firstName, $secondName): bool {
                $byName = collect($backups)->keyBy('name');

                return $byName[$firstName]['format'] === 'JSON v'.BackupService::BACKUP_VERSION
                    && $byName[$firstName]['compatible'] === true
                    && $byName[$firstName]['latest'] === true
                    && $byName[$firstName]['legacy'] === false
                    && $byName[$secondName]['format'] === 'JSON v0'
                    && $byName[$secondName]['compatible'] === false
                    && $byName[$secondName]['latest'] === false
                    && $byName[$secondName]['legacy'] === true;
            });
            $response->assertSee($firstName)
                ->assertSee($secondName)
                ->assertSee('Not compatible with current system')
                ->assertSee('Older backup')
                ->assertSee('Latest')
                ->assertSee('disabled', false)
                ->assertSee('This backup cannot be restored because it is not compatible.');
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

    public function test_unsupported_older_newer_and_other_formats_are_rejected_before_restore(): void
    {
        $package = Package::create([
            'name' => 'Versioned package',
            'slug' => 'versioned-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Must remain unchanged',
        ]);
        $backupService = app(BackupService::class);
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $original = json_decode(Crypt::decryptString(file_get_contents($backupPath)), true, 512, JSON_THROW_ON_ERROR);

        try {
            foreach ([
                ['backup_format' => BackupService::BACKUP_FORMAT, 'backup_version' => 0],
                ['backup_format' => BackupService::BACKUP_FORMAT, 'backup_version' => BackupService::BACKUP_VERSION + 1],
                ['backup_format' => 'OTHER_JSON_BACKUP', 'backup_version' => BackupService::BACKUP_VERSION],
            ] as $incompatibleMetadata) {
                $incompatible = array_merge($original, $incompatibleMetadata);
                file_put_contents($backupPath, Crypt::encryptString(json_encode($incompatible, JSON_THROW_ON_ERROR)), LOCK_EX);

                try {
                    $backupService->restore($backupName);
                    $this->fail('An incompatible backup must not be restored.');
                } catch (\InvalidArgumentException $exception) {
                    $this->assertStringContainsString('not compatible', $exception->getMessage());
                }

                $this->assertSame('Must remain unchanged', $package->fresh()->description);
            }
        } finally {
            $backupService->delete($backupName);
        }
    }

    public function test_unversioned_backup_is_accepted_only_when_it_matches_the_known_legacy_structure(): void
    {
        $backupService = app(BackupService::class);
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $contents = json_decode(Crypt::decryptString(file_get_contents($backupPath)), true, 512, JSON_THROW_ON_ERROR);
        unset($contents['backup_format'], $contents['backup_version'], $contents['created_at']);
        file_put_contents($backupPath, json_encode($contents, JSON_THROW_ON_ERROR), LOCK_EX);

        try {
            $this->expectException(\InvalidArgumentException::class);
            $backupService->validate($backupName);
        } finally {
            $backupService->delete($backupName);
        }
    }

    public function test_existing_versioned_backup_without_format_identifier_remains_compatible(): void
    {
        $package = Package::create([
            'name' => 'Existing format package',
            'slug' => 'existing-format-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Original description',
        ]);
        $backupService = app(BackupService::class);
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $contents = json_decode(Crypt::decryptString(file_get_contents($backupPath)), true, 512, JSON_THROW_ON_ERROR);
        unset($contents['backup_format']);
        file_put_contents($backupPath, Crypt::encryptString(json_encode($contents, JSON_THROW_ON_ERROR)), LOCK_EX);

        try {
            $package->update(['description' => 'Changed description']);

            $this->assertGreaterThan(0, $backupService->restore($backupName));
            $this->assertSame('Original description', $package->fresh()->description);
        } finally {
            $backupService->delete($backupName);
        }
    }

    public function test_version_one_restore_preserves_current_admins_and_audit_history(): void
    {
        $primary = User::factory()->create([
            'name' => 'Current Primary',
            'email' => 'current.primary@example.test',
            'role' => 'full',
            'password' => 'CurrentPrimary#2026',
            'is_active' => true,
        ]);
        $teamAdmin = User::factory()->create([
            'name' => 'Current Team Admin',
            'email' => 'current.team@example.test',
            'role' => 'limited',
            'password' => 'CurrentTeam#2026',
            'is_active' => true,
        ]);
        $disabledAdmin = User::factory()->create([
            'name' => 'Disabled Admin',
            'email' => 'disabled.admin@example.test',
            'role' => 'limited',
            'password' => 'DisabledAdmin#2026',
            'is_active' => false,
        ]);
        $package = Package::create([
            'name' => 'Pre-restore package',
            'slug' => 'pre-restore-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Description from the backup',
        ]);
        ActivityLog::create([
            'user_id' => $primary->id,
            'actor_name' => $primary->name,
            'actor_email' => $primary->email,
            'actor_role' => 'full',
            'action' => 'Archived audit event',
            'method' => 'CLI',
            'activity_date' => now()->subDay()->toDateString(),
            'activity_time' => now()->subDay()->toTimeString(),
            'description' => 'Archived event attribution is preserved.',
        ]);
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);

        try {
            $contents = json_decode(Crypt::decryptString(file_get_contents($backupPath)), true, 512, JSON_THROW_ON_ERROR);
            $contents['backup_version'] = 1;
            $contents['tables']['users'] = User::query()->get()->map(fn (User $user): array => $user->getAttributes())->all();
            foreach ($contents['tables']['users'] as &$archivedUser) {
                $archivedUser['password'] = Hash::make('HistoricalPassword#2020');
                $archivedUser['name'] = 'Historical '.$archivedUser['name'];
                if ($archivedUser['id'] === $teamAdmin->id) {
                    $archivedUser['role'] = 'full';
                }
                if ($archivedUser['id'] === $disabledAdmin->id) {
                    $archivedUser['is_active'] = true;
                }
            }
            unset($archivedUser);
            file_put_contents($backupPath, Crypt::encryptString(json_encode($contents, JSON_THROW_ON_ERROR)), LOCK_EX);

            $package->update(['description' => 'Changed after backup']);
            ActivityLog::create([
                'user_id' => $primary->id,
                'actor_name' => $primary->name,
                'actor_email' => $primary->email,
                'actor_role' => 'full',
                'action' => 'Current audit event',
                'method' => 'CLI',
                'activity_date' => now()->toDateString(),
                'activity_time' => now()->toTimeString(),
                'description' => 'Must survive restore.',
            ]);

            $response = $this->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_user_id' => $primary->id,
                'admin_name' => $primary->name,
                'admin_email' => $primary->email,
                'admin_session_version' => $primary->session_version,
            ])->post(route('admin.backups.restore'), [
                'backup' => $backupName,
                'current_admin_password' => 'CurrentPrimary#2026',
            ]);

            $response->assertRedirect()
                ->assertSessionHas('success');
            $this->assertSame('Description from the backup', $package->fresh()->description);
            $this->assertTrue(Hash::check('CurrentPrimary#2026', $primary->fresh()->password));
            $this->assertSame('full', $primary->fresh()->role);
            $this->assertTrue($primary->fresh()->is_active);
            $this->assertTrue(Hash::check('CurrentTeam#2026', $teamAdmin->fresh()->password));
            $this->assertSame('limited', $teamAdmin->fresh()->role);
            $this->assertTrue($teamAdmin->fresh()->is_active);
            $this->assertSame('limited', $disabledAdmin->fresh()->role);
            $this->assertFalse($disabledAdmin->fresh()->is_active);
            $this->assertDatabaseHas('activity_logs', [
                'action' => 'Current audit event',
                'user_id' => $primary->id,
            ]);
            $this->assertDatabaseHas('activity_logs', [
                'action' => 'Archived audit event',
                'user_id' => null,
                'actor_name' => 'Current Primary',
                'actor_email' => 'current.primary@example.test',
            ]);
            $this->assertDatabaseHas('activity_logs', [
                'action' => 'Backup restored successfully',
                'user_id' => $primary->id,
            ]);

            $this->get(route('admin.dashboard'))->assertOk();
            $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
            $this->post(route('admin.login.post'), [
                'email' => $primary->email,
                'password' => 'CurrentPrimary#2026',
            ])->assertRedirect(route('admin.dashboard'));
            $this->post(route('admin.logout'));
            $this->post(route('admin.login.post'), [
                'email' => $teamAdmin->email,
                'password' => 'CurrentTeam#2026',
            ])->assertRedirect(route('admin.dashboard'));
            $this->post(route('admin.logout'));
            $this->post(route('admin.login.post'), [
                'email' => $disabledAdmin->email,
                'password' => 'DisabledAdmin#2026',
            ])->assertSessionHas('error', 'Your administrator account has been disabled. Please contact a Primary Admin.');
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

    public function test_failed_business_restore_rolls_back_without_touching_authentication(): void
    {
        $admin = User::factory()->create([
            'role' => 'full',
            'password' => 'CurrentPrimary#2026',
            'is_active' => true,
        ]);
        $package = Package::create([
            'name' => 'Rollback package',
            'slug' => 'rollback-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Current description',
        ]);
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $contents = json_decode(Crypt::decryptString(file_get_contents($backupPath)), true, 512, JSON_THROW_ON_ERROR);
        $duplicatePackage = $contents['tables']['packages'][0];
        $duplicatePackage['id'] = $duplicatePackage['id'] + 1;
        $contents['tables']['packages'][] = $duplicatePackage;
        file_put_contents($backupPath, Crypt::encryptString(json_encode($contents, JSON_THROW_ON_ERROR)), LOCK_EX);

        try {
            $package->update(['description' => 'Must roll back to this value']);

            try {
                $backupService->restore($backupName);
                $this->fail('A unique constraint violation should abort the restore.');
            } catch (QueryException) {
                // The transaction must leave the current business and authentication state intact.
            }

            $this->assertSame('Must roll back to this value', $package->fresh()->description);
            $this->assertTrue($admin->fresh()->is_active);
            $this->assertSame('full', $admin->fresh()->role);
            $this->assertTrue(Hash::check('CurrentPrimary#2026', $admin->fresh()->password));
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
        }
    }

    public function test_incompatible_backup_restore_is_blocked_before_safety_backup_creation(): void
    {
        $package = Package::create([
            'name' => 'Controller version package',
            'slug' => 'controller-version-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Keep this data',
        ]);
        $backupService = app(BackupService::class);
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);
        $contents = json_decode(Crypt::decryptString(file_get_contents($backupPath)), true, 512, JSON_THROW_ON_ERROR);
        $contents['backup_version'] = BackupService::BACKUP_VERSION + 1;
        file_put_contents($backupPath, Crypt::encryptString(json_encode($contents, JSON_THROW_ON_ERROR)), LOCK_EX);
        $listedBackups = $backupService->listBackups();
        $admin = User::factory()->create(['role' => 'full', 'password' => 'correct-password']);

        try {
            $response = $this->from('/admin/backups')->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_user_id' => $admin->id,
                'admin_email' => $admin->email,
            ])->post(route('admin.backups.restore'), [
                'backup' => $backupName,
                'current_admin_password' => 'correct-password',
            ]);

            $response->assertRedirect('/admin/backups')
                ->assertSessionHasErrors(['backup' => 'Backup format is not compatible with the current system.']);
            $this->assertSame('Keep this data', $package->fresh()->description);
            $this->assertSame($listedBackups, $backupService->listBackups());
        } finally {
            $backupService->delete($backupName);
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

    public function test_recovery_and_full_system_restore_have_no_public_routes(): void
    {
        $this->get('/admin/recover')->assertNotFound();
        $this->post('/admin/full-restore')->assertNotFound();
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
                $stored = json_decode(
                    Crypt::decryptString(file_get_contents($backupService->pathFor($uploadedBackup))),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
                $this->assertSame(BackupService::BACKUP_FORMAT, $stored['backup_format']);
                $this->assertSame(BackupService::BACKUP_VERSION, $stored['backup_version']);
            }

            $this->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_email' => 'backup-admin@example.test',
            ])->get(route('admin.backups'))
                ->assertOk()
                ->assertViewHas('backups', function (array $backups) use ($uploadedBackups): bool {
                    $uploaded = collect($backups)->firstWhere('name', $uploadedBackups[0]);

                    return $uploaded !== null
                        && $uploaded['format'] === 'JSON v'.BackupService::BACKUP_VERSION
                        && $uploaded['compatible'] === true;
                });
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                if (str_starts_with($backup, 'uploaded-backup-')) {
                    $backupService->delete($backup);
                }
            }
            $backupService->delete(basename($sourceBackupPath));
        }
    }

    public function test_uploaded_version_one_backup_is_upgraded_without_archived_users(): void
    {
        $backupService = app(BackupService::class);
        $sourcePath = $backupService->create();
        $existingBackups = $backupService->listBackups();
        $contents = json_decode(Crypt::decryptString(file_get_contents($sourcePath)), true, 512, JSON_THROW_ON_ERROR);
        $contents['backup_version'] = 1;
        $contents['created_at'] = '2020-01-02T03:04:05.123456+08:00';
        $contents['tables']['users'] = [
            User::factory()->make([
                'role' => 'full',
                'password' => 'ArchivedSecret#2020',
            ])->getAttributes(),
        ];
        $upload = UploadedFile::fake()->createWithContent(
            'version-one-backup.json',
            json_encode($contents, JSON_THROW_ON_ERROR),
            'application/json',
        );

        try {
            $response = $this->from('/admin/backups')->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_email' => 'backup-admin@example.test',
            ])->post(route('admin.backups.upload'), ['backup_file' => $upload]);

            $response->assertRedirect('/admin/backups')->assertSessionHas('success');
            $uploadedBackup = collect(array_diff($backupService->listBackups(), $existingBackups))
                ->first(fn (string $name): bool => str_starts_with($name, 'uploaded-backup-'));
            $this->assertNotNull($uploadedBackup);
            $stored = json_decode(
                Crypt::decryptString(file_get_contents($backupService->pathFor($uploadedBackup))),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
            $this->assertSame(BackupService::BACKUP_VERSION, $stored['backup_version']);
            $this->assertArrayNotHasKey('users', $stored['tables']);
            $this->assertSame('2020-01-02T03:04:05.123456+08:00', $stored['created_at']);
            $backupService->restore($uploadedBackup);
            $this->assertSame(
                '2020-01-02T03:04:05.123456+08:00',
                json_decode(Crypt::decryptString(file_get_contents($backupService->pathFor($uploadedBackup))), true, 512, JSON_THROW_ON_ERROR)['created_at'],
            );
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
            $backupService->delete(basename($sourcePath));
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

    public function test_uploaded_unsupported_version_is_rejected_with_a_clear_compatibility_message(): void
    {
        $backupService = app(BackupService::class);
        $sourcePath = $backupService->create();
        $backupsBeforeUpload = $backupService->listBackups();
        $contents = json_decode(Crypt::decryptString(file_get_contents($sourcePath)), true, 512, JSON_THROW_ON_ERROR);
        $contents['backup_version'] = 0;
        $upload = UploadedFile::fake()->createWithContent(
            'unsupported-backup.json',
            json_encode($contents, JSON_THROW_ON_ERROR),
            'application/json',
        );

        try {
            $response = $this->from('/admin/backups')->withSession([
                'is_admin' => true,
                'admin_role' => 'full',
                'admin_email' => 'backup-admin@example.test',
            ])->post(route('admin.backups.upload'), ['backup_file' => $upload]);

            $response->assertRedirect('/admin/backups')
                ->assertSessionHasErrors([
                    'backup_file' => 'Backup format is not compatible with the current system.',
                ]);
            $this->assertSame($backupsBeforeUpload, $backupService->listBackups());
        } finally {
            $backupService->delete(basename($sourcePath));
        }
    }
}