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
        return $user !== null && $user->is_active === true && Hash::check($password, $user->password);
    }
}