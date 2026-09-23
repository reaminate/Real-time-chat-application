<?php

namespace App\Http\Requests;

use App\Enums\ConversationTypeEnum;
use App\Models\Conversation;
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
        $conversation = $this->route('conversation');
        $conversationId = $conversation instanceof Conversation ? $conversation->id : $conversation;
        return [
            'make_users_admin' => ['array', 'sometimes', 'min:1'],
            'make_users_admin.*' => [
                'integer',
                Rule::exists('conversation_member', 'user_id')->where(
                    fn ($query) => $query->where('conversation_id', $conversationId)->whereNull('left_at')
                ),
            ],
            'name' => ['sometimes', 'string', 'max:10'],
            'created_by' => ['sometimes', 'exists:users,id'],
        ];
    }
}
