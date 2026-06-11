<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Booking;
use App\Models\User;
use App\Support\HashidFactory;
use Tests\TestCase;

/**
 * Unit coverage for the hashid masking primitive (no DB).
 */
class HashidTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        HashidFactory::flush();
    }

    protected function tearDown(): void
    {
        HashidFactory::flush();
        parent::tearDown();
    }

    public function test_encode_is_reversible(): void
    {
        $hash = HashidFactory::encode('ctx', 42);

        $this->assertSame(42, HashidFactory::decode('ctx', $hash));
    }

    public function test_encoded_value_is_not_the_raw_id(): void
    {
        $hash = HashidFactory::encode('ctx', 7);

        $this->assertNotSame('7', $hash);
        $this->assertGreaterThanOrEqual(12, strlen($hash));
        $this->assertDoesNotMatchRegularExpression('/^\d+$/', $hash);
    }

    public function test_sequential_ids_do_not_produce_adjacent_hashes(): void
    {
        // Guards against trivially guessing id+1 from a known hash.
        $a = HashidFactory::encode('ctx', 1000);
        $b = HashidFactory::encode('ctx', 1001);

        $this->assertNotSame($a, $b);
        $this->assertGreaterThan(4, levenshtein($a, $b));
    }

    public function test_decode_rejects_tampered_or_invalid_values(): void
    {
        $this->assertNull(HashidFactory::decode('ctx', 'not-a-real-hash'));
        $this->assertNull(HashidFactory::decode('ctx', ''));
        $this->assertNull(HashidFactory::decode('ctx', '!!!'));
    }

    public function test_contexts_are_isolated(): void
    {
        // The same id encodes differently per context, and a hash minted for
        // one context never decodes against another.
        $a = HashidFactory::encode('App\\Models\\Booking', 5);
        $b = HashidFactory::encode('App\\Models\\User', 5);

        $this->assertNotSame($a, $b);
        $this->assertNull(HashidFactory::decode('App\\Models\\User', $a));
    }

    public function test_trait_helpers_round_trip_per_model(): void
    {
        $bookingHash = Booking::encodeHashid(5);
        $userHash = User::encodeHashid(5);

        $this->assertNotSame($bookingHash, $userHash);
        $this->assertSame(5, Booking::decodeHashid($bookingHash));
        $this->assertSame(5, User::decodeHashid($userHash));
        // Cross-model decode fails (different salt context).
        $this->assertNull(User::decodeHashid($bookingHash));
        $this->assertNull(Booking::decodeHashid(null));
    }
}
