<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Seeder;

class ConversationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     */
    public function run(): void
    {
        $testUser = User::where('email', 'test@example.com')->firstOrFail();
        $others = User::whereKeyNot($testUser->id)->get();

        $directCreators = [$testUser, $testUser, $testUser, ...$others->random(2)->all()];
        foreach ($directCreators as $creator) {
            Conversation::factory()->direct()->create(['created_by' => $creator->id]);
        }

        $groupCreators = [$testUser, ...$others->random(2)->all()];
        foreach ($groupCreators as $creator) {
            Conversation::factory()->group()->create(['created_by' => $creator->id]);
        }
    }
}
