<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminPasswordVerifier
{
    public function verify(Request $request, string $password): bool
    {
        $user = User::find($request->session()->get('admin_user_id'));
        if ($user && Hash::check($password, $user->password)) {
            return true;
        }

        $adminEmail = (string) env('ADMIN_EMAIL', 'admin@3yos.com');
        $adminPassword = (string) env('ADMIN_PASSWORD', 'admin123');

        return hash_equals($adminEmail, (string) $request->session()->get('admin_email', ''))
            && hash_equals($adminPassword, $password);
    }
}