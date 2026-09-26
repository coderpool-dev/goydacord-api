<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EmailVerificationLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_returns_current_profile(): void
    {
        $user = User::factory()->create(['name' => 'Me', 'login' => 'me']);

        Sanctum::actingAs($user);

        $this->getJson('/api/auth/profile')
            ->assertOk()
            ->assertJsonPath('user.login', 'me');
    }

    public function test_can_update_display_name(): void
    {
        $user = User::factory()->create(['name' => 'Old']);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/profile', ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('user.name', 'New Name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('original-pass')]);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/profile', [
            'current_password' => 'wrong-pass',
            'new_password' => 'brand-new-pass',
            'new_password_confirmation' => 'brand-new-pass',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('original-pass', $user->fresh()->password));
    }

    public function test_password_change_succeeds_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('original-pass')]);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/profile', [
            'current_password' => 'original-pass',
            'new_password' => 'brand-new-pass',
            'new_password_confirmation' => 'brand-new-pass',
        ])->assertOk();

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
    }

    public function test_email_change_resets_verification_and_sends_confirmation_link(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'old@example.test',
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/profile', ['email' => 'new@example.test'])
            ->assertOk()
            ->assertJsonPath('user.email', 'new@example.test')
            ->assertJsonPath('user.email_verified', false);

        $user->refresh();
        $this->assertSame('new@example.test', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, EmailVerificationLinkNotification::class);
    }

    public function test_unverified_user_cannot_update_profile_but_can_read_it(): void
    {
        $user = User::factory()->unverified()->create(['login' => 'needsverify']);

        Sanctum::actingAs($user);

        $this->getJson('/api/auth/profile')
            ->assertOk()
            ->assertJsonPath('user.email_verified', false);

        $this->postJson('/api/auth/profile', ['name' => 'Blocked'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'EMAIL_NOT_VERIFIED');
    }

    public function test_avatar_upload_is_stored_under_user_id(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/profile', [
            'avatar' => UploadedFile::fake()->image('me.jpg', 64, 64),
        ])
            ->assertOk()
            ->assertJsonPath('user.avatar', fn (string $url) => str_contains($url, '?v='));

        Storage::disk('public')->assertExists("avatars/{$user->id}.jpg");
        $this->assertDatabaseHas('users', ['id' => $user->id, 'avatar' => "{$user->id}.jpg"]);
    }

    public function test_avatar_rejects_non_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/profile', [
            'avatar' => UploadedFile::fake()->create('virus.pdf', 100, 'application/pdf'),
        ])->assertStatus(422);
    }

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/auth/profile')->assertUnauthorized();
    }
}
