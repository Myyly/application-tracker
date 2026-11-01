<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateProfileRequest extends FormRequest
{
    private const array PROFILE_FIELDS = ['name', 'dateOfBirth', 'gender', 'phone'];

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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'dateOfBirth' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'gender' => ['sometimes', 'nullable', 'integer', Rule::in([
                USER_GENDER_MALE,
                USER_GENDER_FEMALE,
            ])],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (array_intersect(self::PROFILE_FIELDS, array_keys($this->all())) === []) {
                    $validator->errors()->add(
                        'profile',
                        'At least one profile field must be provided.',
                    );
                }
            },
        ];
    }
}
