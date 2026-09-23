<?php

namespace App\Http\Requests;

use App\Enums\MessageTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreMessageRequest extends FormRequest
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
            'reply_to' => ['sometimes', Rule::exists('messages', 'id')->where(
                fn ($query) => $query->where('conversation_id', $this->input('conversation_id'))
            )],
            'type' => ['required', new Enum(MessageTypeEnum::class)],
            'body' => [Rule::requiredIf(fn() => $this->input('type') === MessageTypeEnum::TEXT->value), 'string'],
            'attachment' => [Rule::requiredIf(fn() => $this->input('type') !== MessageTypeEnum::TEXT->value), 'file'],
        ];
    }
}
