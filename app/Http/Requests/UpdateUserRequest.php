<?php

namespace App\Http\Requests;

use App\Enums\AttachmentImageTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
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
            'name' => ['sometimes', 'string', 'alpha'],
            'email' => ['sometimes', 'email', 'unique:users,email'],
            'new_password' => ['sometimes', Password::min(7)->letters()->mixedCase()->numbers()->uncompromised()],
            'password' => [Password::min(7)->letters()->mixedCase()->numbers()->uncompromised(), 'required_with:new_password'],
            'avatar' => [
                'nullable',
                'image',
                'mimetypes:' . implode(',', array_column(AttachmentImageTypeEnum::cases(), 'value')),
                'max:2048',
            ],
        ];
    }
}
