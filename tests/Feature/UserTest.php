<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public static function protectedRoutes(): array
    {
        return [
            'active users' => ['getJson', '/api/active-users'],
            'index' => ['getJson', '/api/user?search=a'],
            'show' => ['getJson', '/api/user/someone'],
            'update' => ['patchJson', '/api/user/someone'],
            'destroy' => ['deleteJson', '/api/user/someone'],
        ];
    }

    #[DataProvider('protectedRoutes')]
    public function test_returns_401_without_token(string $method, string $uri): void
    {
        $this->{$method}($uri)->assertUnauthorized();
    }

    public function test_active_users_returns_only_users_without_last_seen(): void
    {
        $online = User::factory()->create(['last_seen_at' => null]);
        User::factory()->create(['last_seen_at' => now()->subDay()]);
        Sanctum::actingAs($online);

        $this->getJson('/api/active-users')
            ->assertOk()
            ->assertJsonPath('message', 'currently_active_users')
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.email', $online->email);
    }

    public function test_index_searches_by_name_and_excludes_self(): void
    {
        $me = User::factory()->create(['name' => 'Alice Smith']);
        User::factory()->create(['name' => 'Alice Jones']);
        User::factory()->create(['name' => 'Bob Brown']);
        Sanctum::actingAs($me);

        $this->getJson('/api/user?search=Alice')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alice Jones');
    }

    public function test_index_without_search_lists_all_other_users(): void
    {
        Sanctum::actingAs(User::factory()->create());
        User::factory(2)->create();

        $this->getJson('/api/user')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_index_rejects_non_letter_search_with_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/user?search=a%25')->assertUnprocessable()->assertJsonValidationErrors('search');
    }

    public function test_show_finds_user_by_friend_id(): void
    {
        $other = User::factory()->create(['email' => 'bob.brown@example.com']);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/user/bobbrown')
            ->assertOk()
            ->assertJsonPath('email', $other->email)
            ->assertJsonPath('friend_id', 'bobbrown');
    }

    public function test_show_returns_404_for_unknown_friend_id(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/user/nobody')->assertNotFound();
    }

    public function test_update_changes_own_name(): void
    {
        $me = User::factory()->create();
        Sanctum::actingAs($me);

        $this->patchJson("/api/user/{$me->friend_id}", ['name' => 'Renamed'])->assertOk();

        $this->assertSame('Renamed', $me->fresh()->name);
    }

    public function test_update_changes_password_to_new_password(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
        $me = User::factory()->create(['password' => 'Current123']);
        Sanctum::actingAs($me);

        $this->patchJson("/api/user/{$me->friend_id}", ['password' => 'Current123', 'new_password' => 'Changed456'])
            ->assertOk();

        $this->assertTrue(password_verify('Changed456', $me->fresh()->password));
    }

    public function test_update_rejects_taken_email_with_422(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($me);

        $this->patchJson("/api/user/{$me->friend_id}", ['email' => $other->email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_update_forbids_editing_another_user_with_403(): void
    {
        $other = User::factory()->create(['name' => 'Original']);
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/user/{$other->friend_id}", ['name' => 'Hacked', 'password' => 'gghtt'])->assertForbidden();

        $this->assertSame('Original', $other->fresh()->name);
    }

    public function test_destroy_deletes_own_account_with_204(): void
    {
        $me = User::factory()->create();
        Sanctum::actingAs($me);

        $this->deleteJson("/api/user/{$me->friend_id}")->assertNoContent();

        $this->assertModelMissing($me);
    }

    public function test_destroy_forbids_deleting_another_user_with_403(): void
    {
        $other = User::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("/api/user/{$other->friend_id}")->assertForbidden();

        $this->assertModelExists($other);
    }
}
