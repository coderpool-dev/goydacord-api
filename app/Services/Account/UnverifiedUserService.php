<?php

namespace App\Services\Account;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Регистрации без подтверждённой почты: через час они освобождают email и логин. */
class UnverifiedUserService
{
    public const TTL_MINUTES = 60;

    public function __construct(private readonly UserDeletionService $deletion) {}

    public function pruneExpired(): int
    {
        $count = 0;

        $this->expiredQuery()->chunkById(100, function ($users) use (&$count) {
            foreach ($users as $user) {
                $this->deletion->deleteUser($user);
                $count++;
            }
        });

        return $count;
    }

    public function pruneConflictsFor(string $email, string $login): void
    {
        $this->expiredQuery()
            ->where(fn ($query) => $query
                ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
                ->orWhere('login', trim($login)))
            ->each(fn (User $user) => $this->deletion->deleteUser($user));
    }

    /** @return array<string, string> поле => текст ошибки */
    public function registrationConflicts(string $email, string $login): array
    {
        $errors = [];

        $emailUser = User::query()->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])->first();

        if ($emailUser) {
            if ($emailUser->email_verified_at !== null) {
                $errors['email'] = 'Этот email уже зарегистрирован';
            } elseif (! $this->isExpired($emailUser)) {
                $errors['email'] = 'На этот email уже отправлено письмо. Подтвердите аккаунт или подождите час, чтобы зарегистрироваться снова.';
            }
        }

        $loginUser = User::query()->where('login', trim($login))->first();

        if ($loginUser && (int) $loginUser->id !== (int) $emailUser?->id) {
            if ($loginUser->email_verified_at !== null) {
                $errors['login'] = 'Этот логин уже занят';
            } elseif (! $this->isExpired($loginUser)) {
                $errors['login'] = sprintf(
                    'Этот логин временно занят неподтверждённой регистрацией. Попробуйте снова через %s.',
                    $this->formatRetryIn($loginUser),
                );
            }
        }

        return $errors;
    }

    /** @return Builder<User> */
    private function expiredQuery(): Builder
    {
        return User::query()
            ->whereNull('email_verified_at')
            ->where('created_at', '<', $this->expiresBefore())
            ->orderBy('id');
    }

    private function expiresBefore(): Carbon
    {
        return now()->subMinutes(self::TTL_MINUTES);
    }

    private function isExpired(User $user): bool
    {
        return $user->email_verified_at === null && $user->created_at?->lt($this->expiresBefore());
    }

    private function formatRetryIn(User $user): string
    {
        $seconds = now()->diffInSeconds($user->created_at->copy()->addMinutes(self::TTL_MINUTES), false);

        if ($seconds <= 0) {
            return 'несколько минут';
        }

        $minutes = (int) ceil($seconds / 60);

        return $minutes >= 60 ? '1 час' : $minutes.' мин';
    }
}
