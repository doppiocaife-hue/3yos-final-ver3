<?php

namespace App\Services;

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
        'inquiries',
        'activity_logs',
        'settings',
        'notification_templates',
        'gallery_items',
    ];

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

    public function restore(string $backup): int
    {
        $path = $this->pathFor($backup);
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException('The selected backup could not be read.');
        }
        $contents = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $tables = $contents['tables'] ?? null;

        if (! is_array($tables)) {
            throw new \RuntimeException('The selected backup has an invalid format.');
        }

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
        if (preg_match('/^backup-\d{14}(?:-\d+)?\.json$/D', $backup) === 1) {
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

        $files = array_values(array_filter($entries, fn ($file) => preg_match('/^backup-\d{14}(?:-\d+)?\.json$/D', $file) === 1));
        rsort($files, SORT_STRING);

        return $files;
    }
}
