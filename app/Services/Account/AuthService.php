<?php

namespace App\Services\Account;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Notifications\PasswordResetLinkNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/** Регистрация, вход, подтверждение почты и восстановление пароля. */
class AuthService
{
    public function __construct(
        private readonly EmailDeliveryService $emailDelivery,
        private readonly SessionService $sessions,
    ) {}

    /**
     * Новый аккаунт не подтверждён: письмо со ссылкой уходит сразу.
     *
     * @param  array{name: string, email: string, login: string, password: string, date?: string|null}  $data
     */
    public function register(array $data): User
    {
        $this->emailDelivery->ensureAvailable('Регистрация временно недоступна: отправка писем подтверждения не настроена');

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'login' => $data['login'],
            'password' => Hash::make($data['password']),
            'date' => $data['date'] ?? now(),
            'email_verified_at' => null,
        ]);

        $user->sendEmailVerificationLink();

        return $user;
    }

    /**
     * Неподтверждённой почте вход не даём, но заново отправляем ссылку подтверждения.
     *
     * @return array{user: User, token: string}
     */
    public function login(string $email, string $password, Request $request): array
    {
        $user = User::firstWhere('email', $email);

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new ApiException('Неверный пароль или Пользователь не найден', 401);
        }

        if ($user->email_verified_at === null) {
            $this->emailDelivery->ensureAvailable('Почта не подтверждена, но отправка писем подтверждения сейчас не настроена');
            $user->sendEmailVerificationLink();

            throw new ApiException('Подтвердите почту, мы отправили новую ссылку подтверждения', 403, [
                'code' => 'EMAIL_NOT_VERIFIED',
            ]);
        }

        return ['user' => $user, 'token' => $this->sessions->issueToken($user, $request)];
    }

    /** Подпись ссылки проверяет middleware signed, здесь — что ссылка выдана на этот адрес. */
    public function verifyEmail(int $userId, string $emailHash): bool
    {
        $user = User::find($userId);

        if (! $user || ! hash_equals(sha1($user->email), $emailHash)) {
            return false;
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return true;
    }

    /** @return bool false — почта уже подтверждена, письмо не нужно */
    public function resendVerificationEmail(User $user): bool
    {
        if ($user->email_verified_at !== null) {
            return false;
        }

        $this->emailDelivery->ensureAvailable('Отправка писем подтверждения сейчас не настроена');
        $user->sendEmailVerificationLink();

        return true;
    }

    /** Молча ничего не делает для незнакомой почты: по ответу нельзя узнать, зарегистрирован ли адрес. */
    public function sendPasswordResetLink(string $email): void
    {
        $this->emailDelivery->ensureAvailable('Отправка писем восстановления пароля сейчас не настроена');

        $user = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->first();

        if ($user) {
            $user->notify(new PasswordResetLinkNotification($this->resetPasswordUrl($user, Password::createToken($user))));
        }
    }

    /**
     * После смены пароля выходим на всех устройствах.
     *
     * @param  array{email: string, token: string, password: string, password_confirmation?: string}  $credentials
     */
    public function resetPassword(array $credentials): bool
    {
        $status = Password::broker()->reset($credentials, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password)])->save();
            $user->tokens()->delete();
        });

        return $status === Password::PASSWORD_RESET;
    }

    private function resetPasswordUrl(User $user, string $token): string
    {
        return rtrim(config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $user->email,
        ]);
    }
}
