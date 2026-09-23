<?php

namespace App\Http\Requests;

use App\Enums\ConversationTypeEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules;

class StoreConversationRequest extends FormRequest
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
            'users' => ['array', 'required', 'min:1'],
            'users.*' => ['exists:users,id', 'integer'],
            'name' => [Rule::requiredIf(function(){
                return count($this->input('users', [])) > 1;
            }), 'string', 'max:10'],
        ];
    }
}
