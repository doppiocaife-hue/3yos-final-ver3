<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

final class AdminPasswordRules
{
    public static function rules(): array
    {
        return [
            'required',
            'confirmed',
            Password::min(12)->letters()->mixedCase()->numbers()->symbols(),
        ];
    }
}
