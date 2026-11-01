<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ResetPasswordRequest extends FormRequest
{
    private const int MAX_PASSWORD_BYTES = 72;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $password = $this->input('password');

                // Bcrypt accepts at most 72 bytes, which differs from character length for Unicode.
                if (is_string($password) && strlen($password) > self::MAX_PASSWORD_BYTES) {
                    $validator->errors()->add(
                        'password',
                        'The password field must not be greater than 72 bytes.',
                    );
                }
            },
        ];
    }
}
