<?php

namespace App\Http\Requests;

use App\Enums\AttachmentImageTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'regex:/^[\pL\s]+$/u'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'new_password' => ['sometimes', Password::min(7)->letters()->mixedCase()->numbers()->uncompromised()],
            'password' => ['string', 'required_with:new_password', 'current_password'],
            'avatar' => [
                'nullable',
                'image',
                'mimetypes:' . implode(',', array_column(AttachmentImageTypeEnum::cases(), 'value')),
                'max:2048',
            ],
        ];
    }
}
