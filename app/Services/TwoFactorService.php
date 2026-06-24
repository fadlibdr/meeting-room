<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use PragmaRX\Google2FA\Google2FA;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * TOTP two-factor authentication (SOC 2 CC6.1 / ISO 27001 A.8.5): secret
 * generation, code verification, otpauth provisioning URI + inline QR (reusing
 * the bundled QR renderer), and one-time recovery codes.
 */
final class TwoFactorService
{
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(private readonly Google2FA $engine) {}

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey();
    }

    public function verify(string $secret, string $code): bool
    {
        $code = trim(str_replace(' ', '', $code));

        if ($code === '') {
            return false;
        }

        return $this->engine->verifyKey($secret, $code);
    }

    /**
     * otpauth:// provisioning URI for authenticator apps, labelled by the app
     * name and the user's email.
     */
    public function provisioningUri(User $user, string $secret): string
    {
        $issuer = (string) config('app.name', 'SIRRA');

        return $this->engine->getQRCodeUrl($issuer, (string) $user->email, $secret);
    }

    /**
     * Inline SVG QR for the provisioning URI (no external image service).
     */
    public function qrSvg(string $provisioningUri, int $size = 200): string
    {
        return (string) QrCode::format('svg')->size($size)->margin(1)->generate($provisioningUri);
    }

    /**
     * @return list<string> Fresh one-time recovery codes (plaintext — shown once).
     */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn (): string => sprintf('%s-%s',
                strtoupper(bin2hex(random_bytes(3))),
                strtoupper(bin2hex(random_bytes(3))),
            ))
            ->values()
            ->all();
    }
}
