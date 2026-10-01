<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\AdminPasswordRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        return view('admin.users', ['users' => User::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => AdminPasswordRules::rules(),
            'role' => ['required', 'in:full,limited'],
        ]);

        $roleLabel = $data['role'] === 'full' ? 'Primary admin' : 'Team admin';

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => true,
        ]);

        return back()->with('success', $roleLabel . ' created successfully.');
    }

    public function updateName(Request $request, User $user): RedirectResponse
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', "regex:/^[\\pL\\pM][\\pL\\pM\\s.\\x27’\\-]*$/u"],
        ]);
        $oldName = $user->name;
        $newName = $data['name'];

        if ($oldName === $newName) {
            return back()->with('success', 'Administrator name is unchanged.');
        }

        DB::transaction(function () use ($request, $user, $oldName, $newName): void {
            $user->update(['name' => $newName]);
            $this->recordManagementActivity(
                $request,
                'Changed administrator name',
                "Changed {$this->targetRoleLabel($user)} name from '{$oldName}' to '{$newName}'."
            );
        });

        return back()->with('success', 'Administrator name updated successfully.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);
        $enable = (bool) $data['is_active'];

        if (! $enable && $user->role === 'full'
            && User::query()->where('role', 'full')->where('is_active', true)->count() <= 1) {
            return back()->with('error', 'Cannot disable the last active Primary Administrator. At least one active Primary Administrator is required.');
        }

        if (! $enable && (
            (string) $request->session()->get('admin_user_id') === (string) $user->id
            || strcasecmp((string) $request->session()->get('admin_email'), $user->email) === 0
        )) {
            return back()->with('error', 'You cannot disable your own administrator account.');
        }

        if ($user->is_active === $enable) {
            return back()->with('success', 'Administrator account is already '.($enable ? 'active.' : 'disabled.'));
        }

        $error = DB::transaction(function () use ($request, $user, $enable): ?string {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (! $enable && $lockedUser->role === 'full') {
                $activePrimaryAdmins = User::query()
                    ->where('role', 'full')
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->count();

                if ($activePrimaryAdmins <= 1) {
                    return 'Cannot disable the last active Primary Administrator. At least one active Primary Administrator is required.';
                }
            }

            $lockedUser->update([
                'is_active' => $enable,
                'session_version' => $lockedUser->session_version + ($enable ? 0 : 1),
            ]);
            $this->recordManagementActivity(
                $request,
                $enable ? 'Enabled administrator' : 'Disabled administrator',
                ($request->session()->get('admin_name', 'Primary Administrator'))
                    .' '.($enable ? 'enabled ' : 'disabled ')
                    .$this->targetRoleLabel($lockedUser).' '.$lockedUser->name.'.'
            );

            return null;
        });

        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Administrator account '.($enable ? 'enabled' : 'disabled').' successfully.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => AdminPasswordRules::rules(),
        ]);

        $user->update([
            'password' => Hash::make($data['password']),
            'session_version' => $user->session_version + 1,
        ]);

        $this->recordManagementActivity($request, 'Changed administrator password', 'Updated administrator password. Existing sessions were revoked.');

        return back()->with('success', 'Password updated for ' . $user->name . '.');
    }

    private function recordManagementActivity(Request $request, string $action, string $description): void
    {
        ActivityLog::create([
            'user_id' => $request->session()->get('admin_user_id'),
            'actor_name' => $request->session()->get('admin_name', 'Unknown administrator'),
            'actor_email' => $request->session()->get('admin_email'),
            'actor_role' => $request->session()->get('admin_role', 'full'),
            'action' => $action,
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'activity_date' => now()->toDateString(),
            'activity_time' => now()->toTimeString(),
            'description' => $description,
        ]);
    }

    private function targetRoleLabel(User $user): string
    {
        return $user->role === 'full' ? 'Primary Administrator' : 'Team Admin';
    }
}
