<?php

namespace Tests\Feature;

use App\Enums\AttachmentCollectionEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secret123';

    /** The password rules call the pwned-passwords API; answer "not found" so every password passes. */
    private function fakePwnedPasswords(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
    }

    public function test_register_creates_user(): void
    {
        $this->fakePwnedPasswords();

        $response = $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
            'password' => self::PASSWORD,
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'jane.doe@example.com')
            ->assertJsonPath('user.friend_id', 'janedoe')
            ->assertJsonPath('token_type', 'bearer')
            ->assertJsonStructure(['access_token']);
        $this->assertDatabaseHas('users', ['email' => 'jane.doe@example.com', 'friend_id' => 'janedoe']);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_register_stores_avatar_attachment(): void
    {
        $this->fakePwnedPasswords();
        Storage::fake('local');

        $this->post('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => self::PASSWORD,
            'avatar' => UploadedFile::fake()->create('me.png', 10, 'image/png'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $user = User::where('email', 'jane@example.com')->sole();
        $this->assertSame(AttachmentCollectionEnum::AVATAR, $user->avatar->collection);
        Storage::disk('local')->assertExists($user->avatar->path);
    }

    public function test_register_rejects_empty_payload_with_422(): void
    {
        $this->postJson('/api/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_register_rejects_duplicate_email_with_422(): void
    {
        $this->fakePwnedPasswords();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'taken@example.com',
            'password' => self::PASSWORD,
        ])->assertUnprocessable()->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
    }

    public function test_register_rejects_weak_password_with_422(): void
    {
        $this->fakePwnedPasswords();

        $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_login_returns_token_and_marks_user_online(): void
    {
        $this->fakePwnedPasswords();
        $user = User::factory()->create(['password' => self::PASSWORD]);

        $this->postJson('/api/login?'.http_build_query(['email' => $user->email, 'password' => self::PASSWORD]))
            ->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonStructure(['access_token']);

        $this->assertNull($user->fresh()->last_seen_at);
        $this->assertCount(1, $user->tokens);
    }

    public function test_login_rejects_wrong_password_with_422(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        $this->postJson('/api/login?'.http_build_query(['email' => $user->email, 'password' => 'Wrong1234']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['error' => 'wrong_password']);

        $this->assertCount(0, $user->tokens);
    }


    public function test_login_rejects_unknown_email_with_422(): void
    {
        $this->fakePwnedPasswords();

        $this->postJson('/api/login?'.http_build_query(['email' => 'nobody@example.com', 'password' => self::PASSWORD]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $this->fakePwnedPasswords();
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $query = http_build_query(['email' => $user->email, 'password' => 'Wrong1234']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson("/api/login?{$query}")->assertJsonValidationErrors(['error' => 'wrong_password']);
        }

        $this->postJson("/api/login?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['error' => 'too many login attempts']);
    }

    public function test_logout_revokes_current_token_and_sets_last_seen(): void
    {
        $user = User::factory()->create(['last_seen_at' => null]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'logout successful');

        $this->assertCount(0, $user->tokens()->get());
        $this->assertNotNull($user->fresh()->last_seen_at);
    }


    public function test_me_returns_authenticated_user_with_conversations(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.conversations', [])
            ->assertJsonPath('data.created_conversations', []);
    }
}
