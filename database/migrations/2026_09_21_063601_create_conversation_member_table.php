<?php

use App\Enums\ConversationRoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversation_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users','id')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations', 'id')->cascadeOnDelete();
            $table->unique(['user_id', 'conversation_id']);
            $table->foreignId('last_read_id')->nullable()->constrained('messages', 'id')->nullOnDelete();
            $table->enum('role', array_column(ConversationRoleEnum::cases(), 'value'))->default(ConversationRoleEnum::MEMBER->value);
            $table->date('joined_at');
            $table->date('left_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_member');
    }
};
