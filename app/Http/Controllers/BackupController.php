<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\BackupService;
use App\Services\AdminPasswordVerifier;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function index(BackupService $backupService)
    {
        $backups = array_map(function (string $name) use ($backupService): array {
            $path = $backupService->pathFor($name);

            return [
                'name' => $name,
                'encrypted' => $backupService->isEncrypted($name),
                'size' => filesize($path) ?: 0,
            ];
        }, $backupService->listBackups());

        return view('admin.backups', compact('backups'));
    }

    public function createBackup(Request $request, BackupService $backupService)
    {
        try {
            $path = $backupService->create();
        } catch (\Throwable $exception) {
            report($exception);
            $this->recordBackupActivity($request, 'Backup creation failed', 'A database backup could not be created.');

            return back()->with('error', 'The database backup could not be created. Check storage permissions and the application log.');
        }

        $this->recordBackupActivity($request, 'Backup created', 'Created encrypted backup '.basename($path).'.');

        return back()->with('success', 'Database backup created successfully.');
    }

    public function uploadBackup(Request $request, BackupService $backupService)
    {
        $validated = $request->validate([
            'backup_file' => ['required', 'file', 'mimetypes:application/json,text/plain,application/octet-stream', 'extensions:json,enc', 'max:20480'],
        ], [
            'backup_file.required' => 'Please select a backup file.',
            'backup_file.file' => 'The uploaded backup file is invalid.',
            'backup_file.mimetypes' => 'Unsupported backup file type.',
            'backup_file.extensions' => 'Unsupported backup file type.',
            'backup_file.max' => 'Backup file exceeds the maximum allowed size.',
        ]);

        try {
            $backupName = $backupService->upload($validated['backup_file']);
        } catch (\InvalidArgumentException $exception) {
            $this->recordBackupActivity($request, 'Backup upload failed', 'A submitted backup was rejected during verification.');

            return back()->withErrors(['backup_file' => $exception->getMessage()])->withInput();
        } catch (\Throwable $exception) {
            report($exception);
            $this->recordBackupActivity($request, 'Backup upload failed', 'A submitted backup could not be stored.');

            return back()->withErrors(['backup_file' => 'The backup file is invalid or corrupted.'])->withInput();
        }

        $this->recordBackupActivity($request, 'Backup uploaded', 'Uploaded encrypted backup '.$backupName.'.');

        return back()->with('success', 'Backup uploaded successfully.')->withInput(['uploaded_backup' => $backupName]);
    }

    public function restoreBackup(Request $request, BackupService $backupService, AdminPasswordVerifier $passwordVerifier)
    {
        $data = $request->validate([
            'backup' => ['required', 'string', 'regex:/^(?:backup-\d{14}(?:-\d+)?|uploaded-backup-\d{8}(?:-\d{6})?(?:-\d+)?)\.json(?:\.enc)?$/D'],
            'current_admin_password' => ['required', 'string'],
        ]);

        $isLegacy = ! $backupService->isEncrypted($data['backup']);
        if ($isLegacy && ! $request->boolean('confirm_legacy')) {
            return back()->withErrors(['backup' => 'Confirm that you understand this legacy backup is unencrypted before restoring it.'])->withInput(['backup' => $data['backup']]);
        }

        if (! $passwordVerifier->verify($request, $data['current_admin_password'])) {
            $this->recordBackupActivity($request, 'Backup restore failed', 'Password confirmation failed for a restore attempt.');

            return back()->withErrors(['current_admin_password' => 'The password confirmation is incorrect.'])->withInput(['backup' => $data['backup']]);
        }

        try {
            $backupService->validate($data['backup']);
            $safetyBackupPath = $backupService->create();
            $restoredRows = $backupService->restore($data['backup']);
        } catch (\InvalidArgumentException $exception) {
            $this->recordBackupActivity($request, 'Backup restore failed', 'Restore verification failed for '.$data['backup'].'.');

            return back()->withErrors(['backup' => 'Backup verification failed. The backup may be corrupted or modified, or use an unsupported backup version.'])->withInput(['backup' => $data['backup']]);
        } catch (\Throwable $exception) {
            report($exception);
            $this->recordBackupActivity($request, 'Backup restore failed', 'Restore failed for '.$data['backup'].'.');

            return back()->withErrors(['backup' => 'The selected backup could not be restored. Verify that it is a valid backup for this database.'])->withInput(['backup' => $data['backup']]);
        }

        $safetyBackupName = basename($safetyBackupPath);
        $this->recordBackupActivity($request, 'Backup restored successfully', 'Restored '.$data['backup'].' ('.$restoredRows.' rows). Safety backup: '.$safetyBackupName.'.');

        return back()->with('success', "Backup restored successfully ({$restoredRows} rows).");
    }

    public function downloadBackup(Request $request, BackupService $backupService)
    {
        $data = $request->validate(['backup' => ['required', 'string', 'regex:/^(?:backup-\d{14}(?:-\d+)?|uploaded-backup-\d{8}(?:-\d{6})?(?:-\d+)?)\.json(?:\.enc)?$/D']]);

        try {
            $path = $backupService->pathFor($data['backup']);
        } catch (\Throwable $exception) {
            report($exception);
            $this->recordBackupActivity($request, 'Backup download failed', 'A requested backup was unavailable.');

            return back()->withErrors(['backup' => 'The selected backup is unavailable.']);
        }

        $this->recordBackupActivity($request, 'Backup downloaded', 'Downloaded '.($backupService->isEncrypted($data['backup']) ? 'encrypted backup ' : 'legacy unencrypted backup ').$data['backup'].'.');

        return response()->download($path, $data['backup'], [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function deleteBackup(Request $request, BackupService $backupService, AdminPasswordVerifier $passwordVerifier)
    {
        $data = $request->validate([
            'backup' => ['required', 'string', 'regex:/^(?:backup-\d{14}(?:-\d+)?|uploaded-backup-\d{8}(?:-\d{6})?(?:-\d+)?)\.json(?:\.enc)?$/D'],
            'current_admin_password' => ['required', 'string'],
        ]);

        if (! $passwordVerifier->verify($request, $data['current_admin_password'])) {
            $this->recordBackupActivity($request, 'Backup deletion failed', 'Password confirmation failed for a deletion attempt.');

            return back()->withErrors(['current_admin_password' => 'The password confirmation is incorrect.'])->withInput(['backup' => $data['backup']]);
        }

        try {
            $backupService->delete($data['backup']);
        } catch (\Throwable $exception) {
            report($exception);
            $this->recordBackupActivity($request, 'Backup deletion failed', 'Deletion failed for '.$data['backup'].'.');

            return back()->withErrors(['backup' => 'The selected backup could not be deleted.']);
        }

        $this->recordBackupActivity($request, 'Backup deleted', 'Deleted '.$data['backup'].'.');

        return back()->with('success', 'Backup deleted successfully.');
    }

    private function recordBackupActivity(Request $request, string $action, string $description): void
    {
        ActivityLog::create([
            'user_id' => $request->session()->get('admin_user_id'),
            'actor_name' => $request->session()->get('admin_name', 'Unknown administrator'),
            'actor_email' => $request->session()->get('admin_email'),
            'actor_role' => $request->session()->get('admin_role', 'limited'),
            'action' => $action,
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'activity_date' => now()->toDateString(),
            'activity_time' => now()->toTimeString(),
            'description' => $description,
        ]);
    }
}
