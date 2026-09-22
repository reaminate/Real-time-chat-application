<?php

namespace App\Http\Requests;

use App\Enums\AttachmentImageTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'alpha'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required',Password::min(7)->letters()->mixedCase()->numbers()->uncompromised()],
            'avatar' => [
                'nullable',
                'image',
                'mimetypes:' . implode(',', array_column(AttachmentImageTypeEnum::cases(), 'value')),
                'max:2048',
            ],
        ];
    }
}
