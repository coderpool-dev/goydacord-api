<?php

namespace App\Http\Resources;

use App\Models\Account\PersonalAccessToken;
use App\Services\Account\SessionDeviceParser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PersonalAccessToken */
class SessionResource extends JsonResource
{
    public function __construct($resource, private readonly ?int $currentTokenId = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $device = app(SessionDeviceParser::class)->describe($this->user_agent);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'ip_address' => $this->ip_address,
            'country' => $this->country,
            'user_agent' => $this->user_agent,
            'device' => $device['device'],
            'device_type' => $device['device_type'],
            'os' => $device['os'],
            'client' => $device['client'],
            'last_used_at' => $this->last_used_at,
            'created_at' => $this->created_at,
            'expires_at' => $this->expires_at,
            'is_current' => $this->currentTokenId !== null && $this->id === $this->currentTokenId,
        ];
    }
}
