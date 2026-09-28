<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use App\Services\AdminPasswordVerifier;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function index(BackupService $backupService)
    {
        $backups = $backupService->listBackups();

        return view('admin.backups', compact('backups'));
    }

    public function createBackup(BackupService $backupService)
    {
        try {
            $backupService->create();
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'The database backup could not be created. Check storage permissions and the application log.');
        }

        return back()->with('success', 'Database backup created successfully.');
    }

    public function uploadBackup(Request $request, BackupService $backupService)
    {
        $validated = $request->validate([
            'backup_file' => ['required', 'file', 'mimetypes:application/json,text/plain,application/octet-stream', 'extensions:json', 'max:20480'],
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
            return back()->withErrors(['backup_file' => $exception->getMessage()])->withInput();
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['backup_file' => 'The backup file is invalid or corrupted.'])->withInput();
        }

        return back()->with('success', 'Backup uploaded successfully.')->withInput(['uploaded_backup' => $backupName]);
    }

    public function restoreBackup(Request $request, BackupService $backupService, AdminPasswordVerifier $passwordVerifier)
    {
        $data = $request->validate([
            'backup' => ['required', 'string', 'regex:/^(?:backup-\d{14}(?:-\d+)?|uploaded-backup-\d{8}(?:-\d{6})?(?:-\d+)?)\.json$/D'],
            'password_confirmation' => ['required', 'string'],
        ]);

        if (! $passwordVerifier->verify($request, $data['password_confirmation'])) {
            return back()->withErrors(['password_confirmation' => 'The password confirmation is incorrect.'])->withInput(['backup' => $data['backup']]);
        }

        try {
            $restoredRows = $backupService->restore($data['backup']);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['backup' => 'The selected backup could not be restored. Verify that it is a valid backup for this database.'])->withInput(['backup' => $data['backup']]);
        }

        return back()->with('success', "Backup restored successfully ({$restoredRows} rows).");
    }

    public function downloadBackup(Request $request, BackupService $backupService)
    {
        $data = $request->validate(['backup' => ['required', 'string', 'regex:/^(?:backup-\d{14}(?:-\d+)?|uploaded-backup-\d{8}(?:-\d{6})?(?:-\d+)?)\.json$/D']]);

        try {
            return response()->download($backupService->pathFor($data['backup']));
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['backup' => 'The selected backup is unavailable.']);
        }
    }

    public function deleteBackup(Request $request, BackupService $backupService, AdminPasswordVerifier $passwordVerifier)
    {
        $data = $request->validate([
            'backup' => ['required', 'string', 'regex:/^(?:backup-\d{14}(?:-\d+)?|uploaded-backup-\d{8}(?:-\d{6})?(?:-\d+)?)\.json$/D'],
            'password_confirmation' => ['required', 'string'],
        ]);

        if (! $passwordVerifier->verify($request, $data['password_confirmation'])) {
            return back()->withErrors(['password_confirmation' => 'The password confirmation is incorrect.'])->withInput(['backup' => $data['backup']]);
        }

        try {
            $backupService->delete($data['backup']);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['backup' => 'The selected backup could not be deleted.']);
        }

        return back()->with('success', 'Backup deleted successfully.');
    }
}
