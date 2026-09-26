<?php

namespace App\Services\Account;

use App\Events\UserProfileUpdated;
use App\Models\User;
use App\Services\Presence\ActivityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProfileService
{
    /** Поля, которые видят другие пользователи: их изменение рассылаем в общие каналы. */
    private const PUBLIC_FIELDS = [
        'avatar', 'name', 'banner', 'presence', 'status_emoji', 'status_text', 'game_status_text', 'music_status_text',
    ];

    public function __construct(private readonly ActivityService $activityService) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array{user: User, changed: bool}
     */
    public function update(
        User $user,
        array $validated,
        ?UploadedFile $avatar = null,
        ?UploadedFile $banner = null,
    ): array {
        $updateData = $this->attributesFromInput($user, $validated);

        if ($avatar !== null) {
            $updateData['avatar'] = $this->storeImage($avatar, 'avatars', $user->id.'.jpg', $user->avatar, 'default.png');
        }

        if ($banner !== null) {
            $updateData['banner'] = $this->storeImage($banner, 'banners', $user->id.'.jpg', $user->banner);
        } elseif ($validated['remove_banner'] ?? false) {
            $this->deletePublicFile('banners', $user->banner);
            $updateData['banner'] = null;
        }

        if ($validated['clear_game_status'] ?? false) {
            $this->activityService->recordGameEnd($user);
        }

        if ($updateData === []) {
            return ['user' => $user->load('yandexMusicConnection'), 'changed' => false];
        }

        $user->update($updateData);
        $user->refresh()->load('yandexMusicConnection');

        if (array_key_exists('email_verified_at', $updateData) && $user->email_verified_at === null) {
            $user->sendEmailVerificationLink();
        }

        if (array_intersect(self::PUBLIC_FIELDS, array_keys($updateData)) !== []) {
            broadcast(new UserProfileUpdated($user, $user->activeChannelIds()));
        }

        return ['user' => $user, 'changed' => true];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributesFromInput(User $user, array $validated): array
    {
        // null означает «поле не передано»: такие поля не трогаем.
        $updateData = array_filter(
            array_intersect_key($validated, array_flip(['name', 'date', 'presence'])),
            fn ($value) => $value !== null,
        );

        if (isset($validated['email'])) {
            $updateData['email'] = $validated['email'];

            if (strcasecmp($validated['email'], $user->email) !== 0) {
                $updateData['email_verified_at'] = null;
            }
        }

        if (isset($validated['current_password'], $validated['new_password'])) {
            if (! Hash::check($validated['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => ['Текущий пароль неверен']]);
            }

            $updateData['password'] = Hash::make($validated['new_password']);
        }

        if (array_key_exists('banner_color', $validated)) {
            $updateData['banner_color'] = $validated['banner_color'] ?: null;
        }

        if ($validated['clear_status'] ?? false) {
            $updateData['status_emoji'] = null;
            $updateData['status_text'] = null;
        } else {
            foreach (['status_emoji', 'status_text'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $updateData[$field] = $validated[$field] ?: null;
                }
            }
        }

        if ($validated['clear_game_status'] ?? false) {
            $updateData['game_status_text'] = null;
            $updateData['game_status_synced_at'] = null;
        } elseif (array_key_exists('game_status_text', $validated)) {
            $updateData['game_status_text'] = $validated['game_status_text'] ?: null;
            $updateData['game_status_synced_at'] = $validated['game_status_text'] ? now() : null;
        }

        return $updateData;
    }

    private function storeImage(
        UploadedFile $file,
        string $directory,
        string $fileName,
        ?string $previous,
        ?string $keepName = null,
    ): string {
        if (! $file->isValid()) {
            throw new RuntimeException('Загруженный файл повреждён: '.$file->getErrorMessage());
        }

        if ($previous && $previous !== $keepName) {
            $this->deletePublicFile($directory, $previous);
        }

        if ($file->storeAs($directory, $fileName, 'public') === false) {
            throw new RuntimeException("Не удалось сохранить файл на диск. Проверьте права на storage/app/public/{$directory}/");
        }

        return $fileName;
    }

    private function deletePublicFile(string $directory, ?string $name): void
    {
        if ($name) {
            Storage::disk('public')->delete($directory.'/'.$name);
        }
    }
}
