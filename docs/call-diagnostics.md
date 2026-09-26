# Call diagnostics

The frontend sends authenticated batches to `POST /api/webrtc/{call}/diagnostics`.
Deploy backend routes/config and frontend together. Already open pages need a reload;
clients running an older cached bundle cannot produce historical diagnostics.

Logs: `storage/logs/calls-YYYY-MM-DD.log`, newline-delimited JSON, daily rotation,
14 days retained. No database migration. After deploying on a cached Laravel install,
rebuild its route/config caches using the normal deployment procedure.

Each batch includes server `received_at`, authenticated `user_id` / `login`, `call_id`,
`channel_id`, browser/desktop user agent, frontend build time, diagnostic session UUID,
sequence number and cumulative dropped-event count. Client event `at` is wall-clock
time and may differ from server time; `elapsed_ms` is monotonic within the session.
`pc_id` distinguishes recreated connections to the same `peer_id`.

- Every 5 seconds: selected ICE candidate pair and relay protocol when exposed by
  the browser; media RTT in milliseconds, available bandwidth, per-stream interval
  bitrate, packet loss, jitter, FPS, resolution, freezes, NACK/PLI and encoder limits.
- Connection/signaling/ICE changes, candidate error code, track mute/unmute/end,
  network/visibility changes, peer creation/removal and explicit recovery attempts.
- `server_accept`, `server_leave`, `server_heartbeat`: independent backend events,
  including call session ID and heartbeat screen-sharing flag. Accept/leave do not
  carry that flag and its value must not be interpreted as a screen-stop event.

Example: all events for a call, including backend lifecycle:

```sh
jq -c 'select(.context.call_id == "CALL_UUID")' storage/logs/calls-2026-09-17.log
```

Client events with their authenticated sender:

```sh
jq -c 'select(.message == "client_diagnostics" and .context.call_id == "CALL_UUID") | .context as $c | $c.events[] | {login: $c.login, received_at: $c.received_at, session: $c.session_id, event: .}' storage/logs/calls-2026-09-17.log
```

`transport_stats.rtt_ms` is media RTT, not HTTP `/api/ping`. Missing fields are null,
not zero. RTP bitrate/loss need two consecutive samples and reset when counters reset.
Candidate gathering alone is not evidence of a selected route: transport stats only
include selected pairs. `protocol` may be UDP while `relay_protocol` is TCP/TLS.
Track `media=screen` identifies local screen capture; remote video kind alone does
not distinguish a camera from screen sharing.

Transport is best effort: one request at a time, 5-second timeout, 24 events per batch,
120-event memory queue, 30-second backoff after network/server/rate-limit errors.
Failed batches and queue overflow increment `dropped`; no unbounded retry loop or
local persistent storage. 401/403/404/422 disable sending for the current session.
Pending events are flushed on pagehide and call cleanup where the browser permits.
Sudden process termination can lose the tail. Endpoint limit: 30 requests/minute/user.

No SDP, raw ICE candidates, candidate IP addresses, TURN credentials, tokens, media
payloads, track labels, or message contents are included. Peer IDs and measurements
are client-reported; authenticated sender identity is assigned by the server.

The implementation changes diagnostics only, not media bitrate or recovery behavior.
