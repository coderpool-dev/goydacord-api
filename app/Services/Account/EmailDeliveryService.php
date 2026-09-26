<?php

namespace App\Services\Account;

use App\Exceptions\ApiException;

/**
 * Можно ли сейчас отправлять пользователям письма (подтверждение почты, сброс пароля).
 * Драйверы log и array письма никуда не отправляют: на проде это значит, что почта не настроена.
 */
class EmailDeliveryService
{
    public function ensureAvailable(string $message): void
    {
        if (! $this->isAvailable()) {
            throw new ApiException($message, 503, ['code' => 'EMAIL_DELIVERY_NOT_CONFIGURED']);
        }
    }

    public function isAvailable(): bool
    {
        if (! in_array((string) config('mail.default'), ['log', 'array'], true)) {
            return true;
        }

        // В локальной разработке и тестах письма в лог — нормальный режим.
        return app()->environment(['local', 'testing']) || (bool) config('app.debug');
    }
}
