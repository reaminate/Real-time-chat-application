<?php

namespace App\Http\Requests;

use App\Enums\ConversationTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateConversationRequest extends FormRequest
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
            'type' => ['sometimes', new Enum(ConversationTypeEnum::class)],
            'name' => [Rule::requiredIf(function(){
                return $this->input('type') === ConversationTypeEnum::GROUP->value;
            }), 'string', 'max:10'],
            'created_by' => ['sometimes', 'exists:users,id'],
        ];
    }
}
