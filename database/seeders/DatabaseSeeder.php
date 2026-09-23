<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * 10 users, 5 direct + 3 group conversations and 100+ messages.
     * Log in as test@example.com / password to try the API.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);
        User::factory(9)->create();

        // Order matters: each seeder relies on the rows made by the one before it.
        $this->call([
            ConversationSeeder::class,
            ConversationMemberSeeder::class,
            MessageSeeder::class,
            AttachmentSeeder::class,
        ]);
    }
}
