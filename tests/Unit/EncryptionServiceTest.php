<?php

namespace Tests\Unit;

use App\Services\Conversations\EncryptionService;
use Tests\TestCase;

class EncryptionServiceTest extends TestCase
{
    private int $keyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keyId = (int) config('app.encryption_actual');
    }

    public function test_encrypt_returns_key_id_and_base64_gcm_payload(): void
    {
        $encryptedMessage = (new EncryptionService)->encrypt('hello world', $this->keyId);

        $this->assertSame($this->keyId, $encryptedMessage['key_id']);
        $this->assertNotEmpty($encryptedMessage['data']);

        $payload = base64_decode($encryptedMessage['data'], true);
        $this->assertNotFalse($payload);
        $this->assertStringStartsWith('GCM1', $payload, 'Новый формат должен использовать AES-256-GCM');
    }

    public function test_encrypt_then_decrypt_roundtrip(): void
    {
        $service = new EncryptionService;
        $plain = 'Привет, мир! 12345 <tag> "quotes"';

        $encrypted = $service->encrypt($plain, $this->keyId);
        $decrypted = $service->decrypt($encrypted['data'], $encrypted['key_id']);

        $this->assertSame($plain, $decrypted);
    }

    public function test_ciphertext_differs_each_time_due_to_random_iv(): void
    {
        $service = new EncryptionService;

        $first = $service->encrypt('same message', $this->keyId);
        $second = $service->encrypt('same message', $this->keyId);

        $this->assertNotSame($first['data'], $second['data'], 'Случайный IV должен давать разный шифротекст');
    }

    public function test_binary_encrypt_then_decrypt_roundtrip(): void
    {
        $service = new EncryptionService;
        $plain = random_bytes(4096);

        $encrypted = $service->encryptBinary($plain, $this->keyId);

        $this->assertStringStartsWith('GCM1', $encrypted);
        $this->assertNotSame($plain, $encrypted);
        $this->assertSame($plain, $service->decryptBinary($encrypted, $this->keyId));
    }

    public function test_tampered_ciphertext_fails_authentication(): void
    {
        $service = new EncryptionService;
        $encrypted = $service->encrypt('secret', $this->keyId);

        // Портим последний байт шифротекста — GCM-тег не сойдётся.
        $payload = base64_decode($encrypted['data']);
        $payload[strlen($payload) - 1] = $payload[strlen($payload) - 1] === "\x00" ? "\x01" : "\x00";
        $tampered = base64_encode($payload);

        $this->expectException(\Exception::class);
        $service->decrypt($tampered, $this->keyId);
    }

    public function test_unknown_key_id_throws(): void
    {
        $this->expectException(\Exception::class);
        (new EncryptionService)->encrypt('msg', 999);
    }
}
