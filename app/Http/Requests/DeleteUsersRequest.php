<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteUsersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
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
            'delete_users' => ['array', 'required', 'min:1'],
            'delete_users.*' => [
                'integer',
                Rule::exists('conversation_member', 'user_id')->where(
                    fn ($query) => $query->where('conversation_id', $conversationId)->whereNull('left_at')
                ),
            ],
        ];
    }
}
