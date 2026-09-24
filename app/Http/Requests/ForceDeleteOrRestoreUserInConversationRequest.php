<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ForceDeleteOrRestoreUserInConversationRequest extends FormRequest
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
     * This is for the owner to restore or fully delete users from convo
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $conversation = $this->route('conversation');
        $conversationId = $conversation instanceof Conversation ? $conversation->id : $conversation;

        return [
            'users' => ['array', 'required', 'min:1'],
            'users.*' => [
                'integer',
                Rule::exists('conversation_member', 'user_id')->where(
                    fn ($query) => $query->where('conversation_id', $conversationId)->whereNotNull('left_at')
                ),
            ],
        ];
    }
}
