<?php

namespace App\Services\Conversations;

class EncryptionService
{
    /** Магия-префикс нового формата (AES-256-GCM, с проверкой целостности). */
    private const GCM_MAGIC = 'GCM1';

    private const CIPHER_GCM = 'aes-256-gcm';

    private const CIPHER_CBC = 'AES-256-CBC';

    private array $keys;

    public function __construct()
    {
        $this->keys = [
            1 => base64_decode(config('app.encryption_key_1')),
        ];
    }

    public function encrypt(string $message, int $keyId): array
    {
        return [
            'key_id' => $keyId,
            'data' => base64_encode($this->encryptBinary($message, $keyId)),
        ];
    }

    public function encryptBinary(string $plain, int $keyId): string
    {
        $key = $this->keyById($keyId);
        $iv = random_bytes(12);
        $tag = '';

        $encrypted = openssl_encrypt(
            $plain,
            self::CIPHER_GCM,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($encrypted === false) {
            throw new \Exception('Encryption failed');
        }

        return self::GCM_MAGIC.$iv.$tag.$encrypted;
    }

    public function decrypt(string $encoded, int $keyId): string
    {
        $key = $this->keyById($keyId);
        $payload = base64_decode($encoded, true);

        if ($payload === false) {
            throw new \Exception('Decryption failed');
        }

        if (str_starts_with($payload, self::GCM_MAGIC)) {
            return $this->decryptBinary($payload, $keyId);
        }

        // Легаси-формат (AES-256-CBC, без аутентификации) — только для чтения старых сообщений.
        $iv = substr($payload, 0, 16);
        $encrypted = substr($payload, 16);

        $decrypted = openssl_decrypt(
            $encrypted,
            self::CIPHER_CBC,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            throw new \Exception('Decryption failed');
        }

        return $decrypted;
    }

    public function decryptBinary(string $payload, int $keyId): string
    {
        if (! str_starts_with($payload, self::GCM_MAGIC)) {
            throw new \Exception('Decryption failed');
        }

        $key = $this->keyById($keyId);
        $offset = strlen(self::GCM_MAGIC);
        $iv = substr($payload, $offset, 12);
        $tag = substr($payload, $offset + 12, 16);
        $cipher = substr($payload, $offset + 28);

        if (strlen($iv) !== 12 || strlen($tag) !== 16) {
            throw new \Exception('Decryption failed');
        }

        $decrypted = openssl_decrypt(
            $cipher,
            self::CIPHER_GCM,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new \Exception('Decryption failed');
        }

        return $decrypted;
    }

    private function keyById(int $keyId): string
    {
        if (empty($this->keys[$keyId])) {
            throw new \Exception('Encryption key not found');
        }

        return $this->keys[$keyId];
    }
}
