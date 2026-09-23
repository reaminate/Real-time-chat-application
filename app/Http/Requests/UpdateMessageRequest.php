<?php

namespace App\Http\Requests;

use App\Enums\MessageTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMessageRequest extends FormRequest
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
            'conversation_id' => ['required', 'exists:conversations,id'],
            'body' => ['sometimes', 'string'],
            'attachment' => ['sometimes', 'file', 'max:2048'],
        ];
    }
}
