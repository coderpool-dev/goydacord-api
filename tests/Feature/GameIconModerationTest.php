<?php

namespace Tests\Feature;

use App\Events\GameIconUploaded;
use App\Models\Admin\Privilege;
use App\Models\Integrations\GameIcon;
use App\Models\Integrations\GameIconSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GameIconModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Event::fake([GameIconUploaded::class]);
    }

    public function test_user_upload_is_private_until_admin_approves_it(): void
    {
        $uploader = User::factory()->create();
        Sanctum::actingAs($uploader);

        $this->postJson('/api/games/icons', [
            'name' => 'Minecraft',
            'icon' => UploadedFile::fake()->image('icon.jpg', 64, 64),
            'source' => 'folder',
            'hash' => str_repeat('a', 64),
        ])->assertStatus(202)->assertJsonPath('pending', true)->assertJsonPath('exists', false);

        $submission = GameIconSubmission::query()->firstOrFail();
        $this->assertNotSame(str_repeat('a', 64), $submission->icon_hash);
        $this->assertTrue(Storage::disk('local')->exists($submission->file));
        $this->assertSame(0, GameIcon::query()->count());
        $this->getJson('/api/games/icons/lookup?name=Minecraft')->assertJsonPath('exists', false);
        Event::assertNotDispatched(GameIconUploaded::class);

        $this->get("/api/admin/game-icon-submissions/{$submission->id}/preview")->assertForbidden();
        $this->postJson("/api/admin/game-icon-submissions/{$submission->id}/approve", ['source_priority' => 3])->assertForbidden();

        Sanctum::actingAs($this->admin());
        $this->getJson('/api/admin/game-icon-submissions')->assertOk()->assertJsonCount(1, 'submissions');
        $this->get("/api/admin/game-icon-submissions/{$submission->id}/preview")->assertOk();
        $this->postJson("/api/admin/game-icon-submissions/{$submission->id}/approve", ['source_priority' => 2])
            ->assertOk();

        $icon = GameIcon::query()->firstOrFail();
        $this->assertSame(2, $icon->source_priority);
        $this->assertSame($submission->icon_hash, $icon->icon_hash);
        $this->assertTrue(Storage::disk('public')->exists('game-icons/'.$icon->file));
        $this->assertFalse(Storage::disk('local')->exists($submission->file));
        $this->assertSame('approved', $submission->fresh()->status);
        $this->getJson('/api/games/icons/lookup?name=Minecraft')->assertJsonPath('exists', true);
        Event::assertDispatched(GameIconUploaded::class, 1);
        $this->postJson("/api/admin/game-icon-submissions/{$submission->id}/approve", ['source_priority' => 3])
            ->assertUnprocessable();
    }

    public function test_rejection_keeps_published_icon_and_removes_private_file(): void
    {
        Storage::disk('public')->put('game-icons/old.png', 'old image');
        GameIcon::query()->create([
            'slug' => 'minecraft', 'name' => 'Minecraft', 'file' => 'old.png',
            'icon_hash' => str_repeat('0', 64), 'source_priority' => 3,
        ]);
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/games/icons', [
            'name' => 'Minecraft', 'icon' => UploadedFile::fake()->image('new.png', 64, 64),
            'source' => 'folder',
        ])->assertStatus(202)->assertJsonPath('exists', true);
        $submission = GameIconSubmission::query()->firstOrFail();

        Sanctum::actingAs($this->admin());
        $this->postJson("/api/admin/game-icon-submissions/{$submission->id}/reject")->assertOk();

        $this->assertSame('old.png', GameIcon::query()->firstOrFail()->file);
        $this->assertTrue(Storage::disk('public')->exists('game-icons/old.png'));
        $this->assertFalse(Storage::disk('local')->exists($submission->file));
        $this->assertSame('rejected', $submission->fresh()->status);
        Event::assertNotDispatched(GameIconUploaded::class);
    }

    public function test_approval_replaces_existing_icon_only_after_review(): void
    {
        Storage::disk('public')->put('game-icons/old.png', 'old image');
        GameIcon::query()->create([
            'slug' => 'minecraft', 'name' => 'Minecraft', 'file' => 'old.png',
            'icon_hash' => str_repeat('0', 64), 'source_priority' => 3,
        ]);
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/games/icons', [
            'name' => 'Minecraft', 'icon' => UploadedFile::fake()->image('new.webp', 64, 64),
            'source' => 'folder',
        ])->assertStatus(202);
        $this->assertTrue(Storage::disk('public')->exists('game-icons/old.png'));
        $submission = GameIconSubmission::query()->firstOrFail();

        Sanctum::actingAs($this->admin());
        $this->postJson("/api/admin/game-icon-submissions/{$submission->id}/approve", ['source_priority' => 1])->assertOk();

        $icon = GameIcon::query()->firstOrFail();
        $this->assertNotSame('old.png', $icon->file);
        $this->assertSame(1, $icon->source_priority);
        $this->assertTrue(Storage::disk('public')->exists('game-icons/'.$icon->file));
        $this->assertFalse(Storage::disk('public')->exists('game-icons/old.png'));
        Event::assertDispatched(GameIconUploaded::class, 1);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $privilege = Privilege::query()->firstOrCreate(['name' => Privilege::ADMIN]);
        $admin->privileges()->attach($privilege->id);

        return $admin;
    }
}
