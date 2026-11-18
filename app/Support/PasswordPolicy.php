<?php

namespace App\Support;

/**
 * The one password rule. Registration and reset used to differ: reset took any
 * eight characters, so a member could reset to "password". Both now ask for the
 * same thing, and so will the app's endpoints.
 */
final class PasswordPolicy
{
    /** Eight characters or more, with an uppercase letter and a digit. */
    public static function rules(): array
    {
        return ['required', 'string', 'min:8', 'confirmed', 'regex:/[A-Z]/', 'regex:/[0-9]/'];
    }

    public static function message(): string
    {
        return Bilingual::t(
            'Password must contain at least one uppercase letter and one number.',
            'Le mot de passe doit contenir au moins une majuscule et un chiffre.'
        );
    }
}
