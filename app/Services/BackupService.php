<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BackupService
{
    private const TABLES = [
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

    public function create(): string
    {
        $filename = 'backup-' . now()->format('YmdHis');
        $path = storage_path('app/backups/' . $filename);
        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0755, true) && ! is_dir(dirname($path))) {
            throw new \RuntimeException('The backup directory could not be created.');
        }

        $suffix = 1;
        while (is_file($path.'.json')) {
            $path = storage_path('app/backups/'.$filename.'-'.$suffix++);
        }
        $path .= '.json';

        $contents = ['created_at' => now()->toIso8601String(), 'tables' => []];

        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table)) {
                $contents['tables'][$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
            }
        }

        $temporaryPath = $path.'.'.Str::uuid().'.tmp';
        try {
            if (file_put_contents($temporaryPath, json_encode($contents, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false
                || ! rename($temporaryPath, $path)) {
                throw new \RuntimeException('The database backup could not be written.');
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }

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

        $directory = storage_path('app/backups');
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException('The backup directory could not be created.');
        }

        $filename = 'uploaded-backup-' . now()->format('Ymd-His');
        $path = $directory . '/' . $filename . '.json';
        $suffix = 1;
        while (is_file($path)) {
            $path = $directory . '/' . $filename . '-' . $suffix++ . '.json';
        }

        $temporaryPath = $path . '.' . Str::uuid() . '.tmp';
        try {
            $encoded = json_encode($contents, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            if (file_put_contents($temporaryPath, $encoded, LOCK_EX) === false || ! rename($temporaryPath, $path)) {
                throw new \RuntimeException('The uploaded backup could not be stored.');
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }

        return basename($path);
    }

    public function restore(string $backup): int
    {
        $path = $this->pathFor($backup);
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException('The selected backup could not be read.');
        }
        $contents = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertBackupCompatible($contents);

        $tables = $contents['tables'];

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
                    DB::table($table)->delete();
                }

                foreach ($restoreTables as $table) {
                    foreach (array_chunk($rowsByTable[$table], 500) as $chunk) {
                        if ($chunk !== []) {
                            DB::table($table)->insert($chunk);
                            $restoredRows += count($chunk);
                        }
                    }
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $restoredRows;
    }

    public function pathFor(string $backup): string
    {
        if (preg_match('/^(?:backup-\d{14}(?:-\d+)?|uploaded-backup-\d{8}(?:-\d{6})?(?:-\d+)?)\.json$/D', $backup) === 1) {
            $path = storage_path('app/backups/' . $backup);
            if (is_file($path)) {
                return $path;
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
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            return [];
        }

        $entries = scandir($dir);
        if ($entries === false) {
            return [];
        }

        $files = array_values(array_filter($entries, fn ($file) => preg_match('/^(?:backup-\d{14}(?:-\d+)?|uploaded-backup-\d{8}(?:-\d{6})?(?:-\d+)?)\.json$/D', $file) === 1));
        rsort($files, SORT_STRING);

        return $files;
    }

    private function decodeBackup(UploadedFile $file): array
    {
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new \InvalidArgumentException('The backup file is invalid or corrupted.');
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
        if (! array_key_exists('tables', $contents) || ! is_array($contents['tables'])) {
            throw new \InvalidArgumentException('This backup is not compatible with this application version.');
        }

        $tables = $contents['tables'];
        if ($tables === []) {
            throw new \InvalidArgumentException('The backup file is invalid or corrupted.');
        }

        $unexpectedTables = array_diff(array_keys($tables), self::TABLES);
        if ($unexpectedTables !== []) {
            throw new \InvalidArgumentException('This backup is not compatible with this application version.');
        }

        foreach ($tables as $table => $rows) {
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
}
