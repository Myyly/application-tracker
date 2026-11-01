<?php

namespace App\Http\Requests\Auth;

use App\Application\Entity\UserEntity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class RegisterRequest extends FormRequest
{
    private const int MAX_PASSWORD_BYTES = 72;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(UserEntity::TABLE_NAME, UserEntity::EMAIL),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $password = $this->input('password');

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
