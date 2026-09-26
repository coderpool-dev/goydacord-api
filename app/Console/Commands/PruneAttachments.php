<?php

namespace App\Console\Commands;

use App\Models\Conversations\Attachment;
use App\Models\Conversations\Message;
use App\Models\Uploads\UploadSession;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Ретеншн истории: сообщения и их файлы старше uploads.retention_days удаляются.
 * Заодно подчищаются брошенные (недокачанные) сессии загрузки и их временные файлы.
 * Запускается по расписанию (см. bootstrap/app.php).
 */
class PruneAttachments extends Command
{
    protected $signature = 'attachments:prune';

    protected $description = 'Удаляет сообщения и вложения старше срока хранения + брошенные сессии загрузки';

    private const DISK = 'local';

    public function handle(): void
    {
        $this->pruneOldMessages();
        $this->pruneAbandonedUploads();
    }

    private function pruneOldMessages(): void
    {
        $retentionDays = (int) config('uploads.retention_days');
        if ($retentionDays <= 0) {
            $this->info('Ретеншн выключен (retention_days=0).');

            return;
        }

        $cutoff = now()->subDays($retentionDays);
        $deletedFiles = 0;
        $deletedMessages = 0;

        Message::where('created_at', '<', $cutoff)
            ->chunkById(200, function ($batch) use (&$deletedFiles, &$deletedMessages) {
                foreach ($batch as $message) {
                    $path = is_array($message->meta) ? ($message->meta['attachment']['disk_path'] ?? null) : null;
                    if ($path) {
                        Storage::disk(self::DISK)->delete($path);
                        $deletedFiles++;
                    }
                }

                $ids = $batch->pluck('id');
                Attachment::whereIn('message_id', $ids)->delete();
                $deletedMessages += Message::whereIn('id', $ids)->delete();
            });

        $this->info("Ретеншн: удалено сообщений {$deletedMessages}, файлов {$deletedFiles} (старше {$retentionDays} дн).");
    }

    private function pruneAbandonedUploads(): void
    {
        $abandonedBefore = now()->subHours((int) config('uploads.session_ttl_hours'));
        $prunedSessions = 0;

        UploadSession::where('updated_at', '<', $abandonedBefore)
            ->chunkById(100, function ($batch) use (&$prunedSessions) {
                foreach ($batch as $session) {
                    DB::transaction(function () use ($session, &$prunedSessions) {
                        User::whereKey($session->user_id)->lockForUpdate()->first();
                        $current = UploadSession::whereKey($session->id)->lockForUpdate()->first();
                        if (! $current || $current->updated_at >= now()->subHours((int) config('uploads.session_ttl_hours'))) {
                            return;
                        }
                        Storage::disk(self::DISK)->delete($current->tmp_path);
                        // A failed finalization may leave its deterministic destination behind.
                        if ($current->message_id === null) {
                            Storage::disk(self::DISK)->delete($current->finalPath());
                        }
                        $current->delete();
                        $prunedSessions++;
                    });
                }
            });

        if ($prunedSessions > 0) {
            $this->info("Удалено брошенных сессий загрузки: {$prunedSessions}.");
        }
    }
}
