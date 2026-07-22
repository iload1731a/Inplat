<?php

declare(strict_types=1);

namespace App\Validators;

final class AuthValidator
{
    public function validateRegistration(array $input): array
    {
        $errors = [];

        if (($input['username'] ?? '') === '' || strlen((string)$input['username']) < 3) {
            $errors['username'] = 'Username must be at least 3 characters.';
        }

        if (!filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if (strlen((string)($input['password'] ?? '')) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if (($input['password'] ?? '') !== ($input['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        return $errors;
    }

    public function validateForgotPassword(array $input): array
    {
        $errors = [];

        if (!filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }

        return $errors;
    }

    public function validateResetPassword(array $input): array
    {
        $errors = [];

        if (trim((string)($input['token'] ?? '')) === '') {
            $errors['token'] = 'Reset token is required.';
        }

        if (strlen((string)($input['password'] ?? '')) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if (($input['password'] ?? '') !== ($input['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        return $errors;
    }
}
