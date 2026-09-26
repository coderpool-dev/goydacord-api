<?php

namespace App\Http\Requests\WebRTC;

use App\Models\Conversations\Call;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Пачка клиентской диагностики звонка (статистика WebRTC, состояния соединения).
 * Только короткие скалярные поля из белого списка: ни SDP, ни IP, ни произвольного текста.
 */
class StoreCallDiagnosticsRequest extends FormRequest
{
    private const MAX_BODY_BYTES = 60000;

    private const EVENTS = [
        'transport_stats', 'rtp_stats', 'sender_settings', 'peer_created', 'peer_removed', 'connection_state',
        'ice_error', 'track_added', 'track_removed', 'track_state', 'stats_error', 'client_state', 'recovery',
        'screen_event', 'mic_status', 'bootstrap_status', 'connection_unstable',
    ];

    private const FIELDS = [
        'connection', 'ice', 'signaling', 'gathering', 'online', 'visibility', 'pair_id', 'state',
        'rtt_ms', 'available_outgoing_bps', 'available_incoming_bps', 'bytes_sent', 'bytes_received',
        'local_type', 'remote_type', 'protocol', 'relay_protocol', 'remote_protocol', 'remote_relay_protocol',
        'stream_id', 'direction', 'kind', 'track_id', 'codec', 'bitrate_bps', 'packets', 'packets_lost',
        'loss_pct', 'jitter_ms', 'remote_rtt_ms', 'remote_packets_lost', 'remote_fraction_lost',
        'fps', 'width', 'height', 'frames_encoded', 'frames_decoded', 'frames_dropped', 'freeze_count',
        'total_freeze_seconds', 'nack_count', 'pli_count', 'quality_limitation', 'total_encode_seconds',
        'code', 'media', 'ready_state', 'muted', 'enabled', 'max_bitrate', 'max_framerate',
        'degradation', 'content_hint', 'reason', 'attempt',
    ];

    /** Присылать диагностику может только участник звонка. Проверяется до валидации. */
    public function authorize(): bool
    {
        $call = $this->route('call');

        return $call instanceof Call && (bool) $this->user()?->can('signal', $call);
    }

    protected function prepareForValidation(): void
    {
        abort_if(strlen($this->getContent()) > self::MAX_BODY_BYTES, 413);
    }

    public function rules(): array
    {
        return [
            'session_id' => ['required', 'uuid'],
            'sequence' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'dropped' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'build' => ['required', 'string', 'max:100'],
            'events' => ['required', 'array', 'min:1', 'max:24'],
            'events.*' => ['required', 'array:event,at,elapsed_ms,peer_id,pc_id,data'],
            'events.*.event' => ['required', Rule::in(self::EVENTS)],
            'events.*.at' => ['required', 'date'],
            'events.*.elapsed_ms' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'events.*.peer_id' => ['required', 'regex:/^[0-9]{1,20}$/'],
            'events.*.pc_id' => ['required', 'uuid'],
            'events.*.data' => ['present', 'array:'.implode(',', self::FIELDS)],
            'events.*.data.*' => [function (string $attribute, mixed $value, \Closure $fail) {
                if ($value !== null && ! is_bool($value) && ! is_int($value) && ! is_float($value) && ! (is_string($value) && strlen($value) <= 100)) {
                    $fail('Diagnostic values must be short scalars.');
                }
            }],
        ];
    }
}
