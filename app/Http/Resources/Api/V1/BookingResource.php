<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Booking;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Booking API representation. Timestamps are UTC ISO-8601 (clients localise).
 *
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_code' => $this->booking_code,
            'subject' => $this->subject,
            'status' => $this->status->value,
            'resource' => [
                'id' => $this->resource_id,
                'name' => $this->whenLoaded('resource', fn () => $this->resource instanceof Resource ? $this->resource->name : null),
            ],
            'attendee_count' => $this->attendee_count,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
