<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Room
 */
class RoomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'location' => $this->location,
            'floor' => $this->floor,
            'capacity' => $this->capacity,
            'status' => $this->status->value,
            'is_active' => $this->is_active,
        ];
    }
}
