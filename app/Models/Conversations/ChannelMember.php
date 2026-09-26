<?php

namespace App\Models\Conversations;

use App\Enums\MemberCallStatus;
use App\Enums\MembershipStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property bool|null $screen_sharing заполняет CallService::participants
 * @property int|null $screen_preview_at время последнего кадра демонстрации экрана
 */
class ChannelMember extends Model
{
    protected $table = 'channels_members';

    protected $fillable = ['users_id', 'channels_id', 'status', 'call_status', 'last_call_seen', 'last_read_message_id'];

    protected $casts = [
        'status' => MembershipStatus::class,
        'call_status' => MemberCallStatus::class,
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channels_id');
    }

    /** Участник, которого не исключили и который не вышел сам. */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', '!=', MembershipStatus::Removed);
    }
}
