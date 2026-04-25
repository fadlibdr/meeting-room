<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingAttachment>
 */
class BookingAttachmentFactory extends Factory
{
    protected $model = BookingAttachment::class;

    public function definition(): array
    {
        $name = $this->faker->randomElement(['agenda.pdf', 'undangan.docx', 'materi.pptx']);
        $stored = $this->faker->unique()->uuid().'_'.$name;

        return [
            'booking_id' => Booking::factory(),
            'original_name' => $name,
            'stored_name' => $stored,
            'disk' => 'local_private',
            'path' => 'attachments/'.$stored,
            'mime_type' => 'application/pdf',
            'size_bytes' => $this->faker->numberBetween(50_000, 5_000_000),
            'uploaded_by_user_id' => User::factory(),
        ];
    }
}
