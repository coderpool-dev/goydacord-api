<?php

namespace App\Models\Uploads;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UploadSession extends Model
{
    use HasUuids;

    protected $table = 'upload_sessions';

    // id — uuid, не автоинкремент.
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'channel_id',
        'filename',
        'mime',
        'total_size',
        'received_size',
        'tmp_path',
        'message_id',
    ];

    protected $casts = [
        'total_size' => 'integer',
        'received_size' => 'integer',
        'message_id' => 'integer',
    ];

    public function finalPath(): string
    {
        return "attachments/uploads/{$this->id}";
    }
}
