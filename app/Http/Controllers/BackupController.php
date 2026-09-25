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

    public function restoreBackup(Request $request, BackupService $backupService, AdminPasswordVerifier $passwordVerifier)
    {
        $data = $request->validate([
            'backup' => ['required', 'string', 'regex:/^backup-\d{14}(?:-\d+)?\.json$/D'],
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
        $data = $request->validate(['backup' => ['required', 'string', 'regex:/^backup-\d{14}(?:-\d+)?\.json$/D']]);

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
            'backup' => ['required', 'string', 'regex:/^backup-\d{14}(?:-\d+)?\.json$/D'],
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
