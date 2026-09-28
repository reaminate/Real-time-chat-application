<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RemovePinRequest extends FormRequest
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
            'remove_pin_message' => ['required', Rule::exists('messages', 'id')->where(
                fn ($query) => $query->where('conversation_id', $this->route('conversation')->id)->whereNull('deleted_at')
            )->where('is_pinned', true)],
        ];
    }
}
