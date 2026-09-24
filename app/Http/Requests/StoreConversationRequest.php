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
     * always include the creator in users, and cast ids to ints so comparisons work
     */
    protected function prepareForValidation(): void
    {
        $users = $this->input('users');
        if (! is_array($users)) {
            return;
        }
        $users[] = $this->user()->id;
        $this->merge([
            'users' => array_values(array_unique(array_map('intval', $users))),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     * users includes the creator, so 2 users is a direct convo and more is a group
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'users' => ['array', 'required', 'min:2'],
            'users.*' => ['exists:users,id', 'integer', 'distinct'],
            'name' => [Rule::requiredIf(function () {
                return count($this->input('users', [])) > 2;
            }), 'nullable', 'string', 'max:10'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'users.min' => 'A conversation needs at least one other user.',
        ];
    }
}
