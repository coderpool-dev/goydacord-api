<?php

namespace App\Console\Commands;

use App\Models\Conversations\Message;
use App\Services\Conversations\EncryptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class EncryptAttachments extends Command
{
    protected $signature = 'attachments:encrypt-existing';

    protected $description = 'Encrypt existing unencrypted message attachments at rest';

    public function handle(EncryptionService $encryption): int
    {
        $disk = Storage::disk('local');
        $keyId = (int) config('app.encryption_actual');
        $encryptedCount = 0;
        $missingCount = 0;

        Message::query()
            ->whereIn('type', ['image', 'file'])
            ->orderBy('id')
            ->chunkById(100, function ($messages) use ($disk, $encryption, $keyId, &$encryptedCount, &$missingCount): void {
                foreach ($messages as $message) {
                    $meta = is_array($message->meta) ? $message->meta : [];
                    $attachment = $meta['attachment'] ?? null;

                    if (! is_array($attachment) || ($attachment['encrypted'] ?? false) === true) {
                        continue;
                    }

                    $oldPath = (string) ($attachment['disk_path'] ?? '');
                    if ($oldPath === '' || ! $disk->exists($oldPath)) {
                        $missingCount++;
                        $this->warn("Attachment missing for message {$message->id}: {$oldPath}");

                        continue;
                    }

                    $plain = $disk->get($oldPath);
                    if ($plain === null) {
                        $missingCount++;
                        $this->warn("Attachment unreadable for message {$message->id}: {$oldPath}");

                        continue;
                    }

                    $newPath = $oldPath.'.enc';
                    if (! $disk->put($newPath, $encryption->encryptBinary($plain, $keyId))) {
                        $this->error("Failed to encrypt attachment for message {$message->id}");

                        continue;
                    }

                    $attachment['disk_path'] = $newPath;
                    $attachment['encrypted'] = true;
                    $attachment['key_id'] = $keyId;
                    $meta['attachment'] = $attachment;

                    try {
                        $message->update([
                            'meta' => $meta,
                            'key_id' => $message->key_id ?: $keyId,
                        ]);
                    } catch (\Throwable $error) {
                        $disk->delete($newPath);
                        throw $error;
                    }

                    $disk->delete($oldPath);
                    $encryptedCount++;
                }
            });

        $this->info("Encrypted attachments: {$encryptedCount}. Missing: {$missingCount}.");

        return self::SUCCESS;
    }
}
