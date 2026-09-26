# API

HTTP API lives under Laravel's `/api` prefix. Named routes and the current
middleware stack are available with `php artisan route:list --path=api -v`.
`routes/api.php` defines the access levels; `routes/api/` keeps the endpoint
definitions by domain. Files are loaded in a fixed order to preserve existing
route matching for web and desktop clients.

## Access

- Public endpoints use purpose-specific throttles where relevant.
- User endpoints require `auth:sanctum` and demo-account restrictions.
- Most application features also require a verified email. Profile recovery,
  session management and storage management are available before verification.
- Admin endpoints add the `admin` middleware.
- Attachment URLs use Laravel's signed URL middleware because browser image and
  media requests cannot attach the user's bearer token.
- Private broadcast channels are authorized in `routes/channels.php`.

## Endpoint map

| Area | Main routes | Notes |
|---|---|---|
| Authentication | `POST /login`, `/register`, `/forgot-password`, `/reset-password`; `POST /demo`; `GET /email/verify/{id}/{hash}` | Login, registration and recovery have separate limits; email verification uses a signed URL. |
| Profile and sessions | `GET /auth/profile`, `POST /auth/profile`, `GET /auth/sessions`, `DELETE /auth/sessions/{tokenId}`, `POST /auth/logout-all` | Profile update uses multipart form data. |
| Channels and messages | `/channels`, `/channels/{channel}/members`, `/messages`, `/messages/{message}` | Message edits use `PATCH`; deletes use `DELETE`; reactions are a `POST` command because they toggle state. |
| Chunked uploads | `POST /uploads`, `GET /uploads/{upload}`, `PATCH /uploads/{upload}`, `POST /uploads/{upload}/complete` | `PATCH` appends bytes at the `Upload-Offset`; completion is an idempotent command. |
| Calls and WebRTC | `/calls/{channel}`, `/webrtc/{call}/signal`, `/ice-servers`, `/livekit` | Accept, leave, heartbeat and signaling are actions, so they use `POST`. |
| Servers | `/servers`, `/servers/{server}/members`, `/roles`, `/channels`, `/bans`, `/invites` | Nested members, roles, bans and invites are resolved within their parent server. |
| Server messages and voice | `/server-channel-messages`, `/server-channels/{serverChannel}/calls` | Text messages share the message service with direct and group chats. |
| Friends and notifications | `/friends`, `/friends/requests`, `/blocked-users`, `/notification-mutes`, `/push/subscriptions` | User identities in friend routes use login; mutations are authorized by policies and services. |
| Support and administration | `/support`, `/reports`, `/feedback`, `/admin/*` | Admin routes are behind the `admin` middleware. |
| Integrations and activity | `/integrations/yandex-music`, `/activity/game`, `/users/{user}/activity`, `/link-preview` | External lookups and frequently called endpoints have dedicated rate limits. |

## Compatibility and HTTP methods

Existing web and desktop clients share this API. Multipart updates for channels,
servers and profiles use `POST` because PHP does not populate multipart form
fields for `PATCH` requests. These routes retain their existing methods for
backward compatibility.

JSON role updates use `PATCH /servers/{server}/roles/{role}`. The older `POST`
route remains available to deployed clients. Route names are prefixed by their
domain, such as `servers.roles.update`, `uploads.complete` and `messages.store`.
Resource route names end in an action (`index`, `show`, `store`, `update`,
`destroy`); commands use their intent (`calls.accept`, `uploads.complete`,
`messages.reactions.toggle`). Signed URL names remain stable.

Command-style operations such as marking a channel read, joining a server,
accepting a call or toggling a reaction use `POST`; they perform a state
transition rather than replacing a resource representation.

## Error and authorization behavior

Form Requests validate input, policies authorize actions, and services enforce
domain rules. API errors use a JSON envelope with `status` and `message`;
validation errors also include field-level `errors`. Some owned resources return
404 when the caller does not own them to avoid confirming that the resource
exists.

Useful checks while working on routes:

```bash
php artisan route:list --path=api -v
php artisan route:cache
```
