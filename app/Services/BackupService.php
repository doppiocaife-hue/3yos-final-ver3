<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BackupService
{
    private const TABLES = [
        'services',
        'packages',
        'clients',
        'reservations',
        'reservation_payments',
        'reservation_refunds',
        'inquiries',
        'activity_logs',
        'settings',
        'notification_templates',
        'gallery_items',
    ];

    private const LEGACY_TABLES = [
        'users',
        'services',
        'packages',
        'clients',
        'reservations',
        'reservation_payments',
        'reservation_refunds',
        'inquiries',
        'activity_logs',
        'settings',
        'notification_templates',
        'gallery_items',
    ];

    private const MAX_UPLOAD_SIZE = 20 * 1024 * 1024;
    public const BACKUP_FORMAT = '3YOS_JSON_BACKUP';

    public const BACKUP_VERSION = 2;

    public function currentFormat(): array
    {
        return [
            'name' => 'JSON v'.self::BACKUP_VERSION,
            'identifier' => self::BACKUP_FORMAT,
            'version' => self::BACKUP_VERSION,
        ];
    }

    public function inspect(string $backup): array
    {
        $path = $this->pathFor($backup);

        try {
            $contents = $this->readBackup($backup);
        } catch (\InvalidArgumentException) {
            return [
                'format' => 'Unknown format',
                'legacy' => false,
                'compatible' => false,
                'created_at' => null,
                'sort_timestamp' => null,
            ];
        }

        $hasVersion = array_key_exists('backup_version', $contents);
        $version = $contents['backup_version'] ?? null;
        $hasKnownFormat = ! array_key_exists('backup_format', $contents)
            || $contents['backup_format'] === self::BACKUP_FORMAT;
        $format = $hasVersion && (is_int($version) || is_string($version)) && $hasKnownFormat
            ? 'JSON v'.$version
            : (! $hasVersion && $this->isRecognizedLegacyBackup($contents) ? 'Legacy format' : 'Unknown format');

        $createdAt = null;
        if (isset($contents['created_at'])
            && is_string($contents['created_at'])
            && preg_match('/(?:Z|[+-]\d{2}:\d{2})$/i', $contents['created_at']) === 1) {
            try {
                $createdAt = Carbon::parse($contents['created_at'])->setTimezone(config('app.timezone'));
            } catch (\Exception) {
                $createdAt = null;
            }
        }

        try {
            $this->assertBackupCompatible($contents);
            $compatible = true;
        } catch (\InvalidArgumentException) {
            $compatible = false;
        }

        return [
            'format' => $format,
            'legacy' => ! $hasVersion || (is_numeric($version) && (float) $version < self::BACKUP_VERSION),
            'compatible' => $compatible,
            'created_at' => $createdAt,
            'sort_timestamp' => $createdAt === null ? null : (float) $createdAt->format('U.u'),
        ];
    }

    public function create(): string
    {
        $contents = [
            'backup_format' => self::BACKUP_FORMAT,
            'backup_version' => self::BACKUP_VERSION,
            'tables' => [],
        ];

        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table)) {
                $contents['tables'][$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
            }
        }

        $createdAt = now(config('app.timezone'));
        $contents['created_at'] = $createdAt->format('Y-m-d\TH:i:s.uP');

        $filename = 'backup-'.$createdAt->format('YmdHis');
        $directory = $this->privateBackupDirectory();
        $this->ensureDirectory($directory);

        $path = $directory.'/'.$filename.'.json.enc';
        $suffix = 1;
        while (is_file($path)) {
            $path = $directory.'/'.$filename.'-'.$suffix++.'.json.enc';
        }

        $json = json_encode($contents, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $this->writeAtomically($path, Crypt::encryptString($json));

        return $path;
    }

    public function upload(UploadedFile $file): string
    {
        if (! $file->isValid()) {
            throw new \InvalidArgumentException('The backup file is invalid or corrupted.');
        }

        if ($file->getSize() > self::MAX_UPLOAD_SIZE) {
            throw new \InvalidArgumentException('Backup file exceeds the maximum allowed size.');
        }

        $contents = $this->decodeBackup($file);
        $this->assertBackupCompatible($contents);

        $contents['tables'] = $this->detachArchivedUserReferences($contents['tables']);
        unset($contents['tables']['users']);

        $directory = $this->privateBackupDirectory();
        $this->ensureDirectory($directory);

        $filename = 'uploaded-backup-'.now()->format('Ymd-His');
        $path = $directory.'/'.$filename.'.json.enc';
        $suffix = 1;
        while (is_file($path)) {
            $path = $directory.'/'.$filename.'-'.$suffix++.'.json.enc';
        }

        $contents['backup_format'] = self::BACKUP_FORMAT;
        $contents['backup_version'] = self::BACKUP_VERSION;
        $encoded = json_encode($contents, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $this->writeAtomically($path, Crypt::encryptString($encoded));

        return basename($path);
    }

    public function restore(string $backup): int
    {
        $contents = $this->readBackup($backup);
        $this->assertBackupCompatible($contents);

        $tables = $this->detachArchivedUserReferences($contents['tables']);

        $restoreTables = array_values(array_filter(self::TABLES, fn ($table) => array_key_exists($table, $tables) && Schema::hasTable($table)));
        $rowsByTable = [];
        $columnsByTable = [];
        foreach ($restoreTables as $table) {
            $rows = $tables[$table];
            if (! is_array($rows) || ! array_is_list($rows)) {
                throw new \RuntimeException("Invalid data for {$table}.");
            }

            $columnsByTable[$table] = array_flip(Schema::getColumnListing($table));
            $rowsByTable[$table] = array_map(function ($row) use ($table, $columnsByTable) {
                if (! is_array($row)) {
                    throw new \RuntimeException("Invalid row data for {$table}.");
                }

                $compatibleRow = array_intersect_key($row, $columnsByTable[$table]);
                if ($compatibleRow === []) {
                    throw new \RuntimeException("No compatible columns were found for {$table}.");
                }

                return $compatibleRow;
            }, $rows);
        }
        $restoredRows = 0;

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($restoreTables, $rowsByTable, &$restoredRows): void {
                // Backups made before payment history existed carry no payment rows; clear the current
                // ones so they cannot attach to different restored reservations with the same ids.
                if (in_array('reservations', $restoreTables, true) && ! in_array('reservation_payments', $restoreTables, true) && Schema::hasTable('reservation_payments')) {
                    DB::table('reservation_payments')->delete();
                }
                if (in_array('reservations', $restoreTables, true) && ! in_array('reservation_refunds', $restoreTables, true) && Schema::hasTable('reservation_refunds')) {
                    DB::table('reservation_refunds')->delete();
                }

                foreach (array_reverse($restoreTables) as $table) {
                    if ($table !== 'activity_logs') {
                        DB::table($table)->delete();
                    }
                }

                foreach ($restoreTables as $table) {
                    foreach (array_chunk($rowsByTable[$table], 500) as $chunk) {
                        if ($chunk !== []) {
                            if ($table === 'activity_logs') {
                                foreach ($chunk as $row) {
                                    if (! DB::table($table)->where($row)->exists()) {
                                        DB::table($table)->insert($row);
                                        $restoredRows++;
                                    }
                                }
                            } else {
                                DB::table($table)->insert($chunk);
                                $restoredRows += count($chunk);
                            }
                        }
                    }
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $restoredRows;
    }

    public function validate(string $backup): void
    {
        $this->assertBackupCompatible($this->readBackup($backup));
    }

    public function pathFor(string $backup): string
    {
        if (preg_match('/^(?:backup-\d{14}(?:-\d+)?|uploaded-backup-\d{8}(?:-\d{6})?(?:-\d+)?)\.json(?:\.enc)?$/D', $backup) === 1) {
            foreach ([$this->privateBackupDirectory(), storage_path('app/backups')] as $directory) {
                $path = $directory.'/'.$backup;
                if (is_file($path)) {
                    return $path;
                }
            }
        }

        throw new \InvalidArgumentException('Invalid backup file.');
    }

    public function delete(string $backup): void
    {
        if (! unlink($this->pathFor($backup))) {
            throw new \RuntimeException('The selected backup could not be deleted.');
        }
    }

    public function listBackups(): array
    {
        $files = [];
        foreach ([$this->privateBackupDirectory(), storage_path('app/backups')] as $directory) {
            if (! is_dir($directory)) {
                continue;
            }
            $entries = scandir($directory);
            if ($entries !== false) {
                $files = array_merge($files, array_filter(
                    $entries,
                    fn (string $file) => preg_match('/^(?:backup-\d{14}(?:-\d+)?|uploaded-backup-\d{8}(?:-\d{6})?(?:-\d+)?)\.json(?:\.enc)?$/D', $file) === 1
                ));
            }
        }

        $files = array_values(array_unique($files));
        rsort($files, SORT_STRING);

        return $files;
    }

    private function decodeBackup(UploadedFile $file): array
    {
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new \InvalidArgumentException('The backup file is invalid or corrupted.');
        }

        if (strtolower($file->getClientOriginalExtension()) === 'enc') {
            $contents = $this->decryptBackup($contents);
        } elseif (strtolower($file->getClientOriginalExtension()) !== 'json') {
            throw new \InvalidArgumentException('Unsupported backup file type.');
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('The backup file is invalid or corrupted.');
        }

        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('The backup file is invalid or corrupted.');
        }

        return $decoded;
    }

    private function assertBackupCompatible(array $contents): void
    {
        $hasFormat = array_key_exists('backup_format', $contents);
        $hasVersion = array_key_exists('backup_version', $contents);
        $version = $contents['backup_version'] ?? null;

        if (($hasFormat && $contents['backup_format'] !== self::BACKUP_FORMAT)
            || ($hasVersion && ! in_array($version, [1, self::BACKUP_VERSION], true))
            || ($hasFormat && ! $hasVersion)) {
            throw new \InvalidArgumentException('Backup format is not compatible with the current system.');
        }

        if (! $hasVersion && ! $this->isRecognizedLegacyBackup($contents)) {
            throw new \InvalidArgumentException('Backup format is not compatible with the current system.');
        }

        if (! array_key_exists('tables', $contents) || ! is_array($contents['tables'])) {
            throw new \InvalidArgumentException('Backup format is not compatible with the current system.');
        }

        $tables = $contents['tables'];
        if ($tables === []) {
            throw new \InvalidArgumentException('The backup file is invalid or corrupted.');
        }

        $allowedTables = ! $hasVersion || $version === 1 ? self::LEGACY_TABLES : self::TABLES;
        $unexpectedTables = array_diff(array_keys($tables), $allowedTables);
        if ($unexpectedTables !== []) {
            throw new \InvalidArgumentException('Backup format is not compatible with the current system.');
        }

        foreach ($tables as $table => $rows) {
            if (! is_string($table) || ! in_array($table, $allowedTables, true)) {
                throw new \InvalidArgumentException('Backup format is not compatible with the current system.');
            }
            if (! is_array($rows) || ! array_is_list($rows)) {
                throw new \InvalidArgumentException("The backup file is invalid or corrupted for {$table}.");
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    throw new \InvalidArgumentException('The backup file is invalid or corrupted.');
                }
            }
        }
    }

    private function isRecognizedLegacyBackup(array $contents): bool
    {
        if (array_key_exists('backup_format', $contents)
            || array_key_exists('backup_version', $contents)
            || array_diff(array_keys($contents), ['created_at', 'tables']) !== []
            || ! isset($contents['created_at'])
            || ! is_string($contents['created_at'])
            || strtotime($contents['created_at']) === false) {
            return false;
        }

        return is_array($contents['tables']);
    }

    private function detachArchivedUserReferences(array $tables): array
    {
        $usersById = collect($tables['users'] ?? [])->keyBy('id');

        foreach ($tables['activity_logs'] ?? [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $backupUser = $usersById->get($row['user_id'] ?? null);
            foreach (['name' => 'actor_name', 'email' => 'actor_email', 'role' => 'actor_role'] as $userField => $actorField) {
                if (empty($row[$actorField]) && is_array($backupUser) && isset($backupUser[$userField])) {
                    $row[$actorField] = $backupUser[$userField];
                }
            }

            $row['user_id'] = null;
            unset($row['id']);
            $tables['activity_logs'][$index] = $row;
        }

        return $tables;
    }

    public function isEncrypted(string $backup): bool
    {
        return str_ends_with($backup, '.json.enc');
    }

    private function readBackup(string $backup): array
    {
        $raw = file_get_contents($this->pathFor($backup));
        if ($raw === false) {
            throw new \RuntimeException('The selected backup could not be read.');
        }
        if ($this->isEncrypted($backup)) {
            $raw = $this->decryptBackup($raw);
        }

        try {
            $contents = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('Backup verification failed. The backup may be corrupted or modified.');
        }

        if (! is_array($contents)) {
            throw new \InvalidArgumentException('Backup verification failed. The backup may be corrupted or modified.');
        }

        return $contents;
    }

    private function decryptBackup(string $contents): string
    {
        try {
            return Crypt::decryptString($contents);
        } catch (DecryptException) {
            throw new \InvalidArgumentException('Backup verification failed. The backup may be corrupted or modified.');
        }
    }

    private function privateBackupDirectory(): string
    {
        return storage_path('app/private/backups');
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException('The backup directory could not be created.');
        }
    }

    private function writeAtomically(string $path, string $contents): void
    {
        $temporaryPath = $path.'.'.Str::uuid().'.tmp';
        try {
            if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false || ! rename($temporaryPath, $path)) {
                throw new \RuntimeException('The backup file could not be written.');
            }
            @chmod($path, 0600);
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}
