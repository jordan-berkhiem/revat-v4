<?php

namespace App\Services;

use App\Contracts\TwoFactorAuthenticatable;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    /**
     * Generate a new TOTP secret.
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Get the otpauth:// URL for QR code generation.
     */
    public function getQrCodeUrl(TwoFactorAuthenticatable $user, string $appName): string
    {
        return $this->google2fa->getQRCodeUrl(
            $appName,
            $user->getTwoFactorLabel(),
            $user->getTwoFactorSecret(),
        );
    }

    /**
     * Get the SVG QR code for 2FA setup.
     */
    public function getQrCodeSvg(TwoFactorAuthenticatable $user, string $appName): string
    {
        $url = $this->getQrCodeUrl($user, $appName);

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd,
        );

        $writer = new Writer($renderer);

        return $writer->writeString($url);
    }

    /**
     * Verify a TOTP code against the user's secret.
     */
    public function verifyCode(TwoFactorAuthenticatable $user, string $code): bool
    {
        return $this->google2fa->verifyKey(
            $user->getTwoFactorSecret(),
            $code,
            1, // Allow 1 adjacent window for clock drift
        );
    }

    /**
     * Enable 2FA by verifying code and setting confirmed_at.
     */
    public function enable(TwoFactorAuthenticatable $user, string $code): bool
    {
        if (! $this->verifyCode($user, $code)) {
            return false;
        }

        $user->setTwoFactorConfirmedAt(now());

        return true;
    }

    /**
     * Disable 2FA, clearing secret and confirmation timestamp.
     */
    public function disable(TwoFactorAuthenticatable $user): void
    {
        $user->setTwoFactorSecret(null);
        $user->setTwoFactorConfirmedAt(null);
    }

    /**
     * Generate 8 single-use recovery codes in XXXX-XXXX format.
     *
     * Returns both the plain codes (for display) and hashed codes (for storage).
     *
     * @return array{plain: string[], hashed: string[]}
     */
    public function generateRecoveryCodes(): array
    {
        $plain = [];
        $hashed = [];

        for ($i = 0; $i < 8; $i++) {
            $code = strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
            $plain[] = $code;
            $hashed[] = Hash::make($code);
        }

        return [
            'plain' => $plain,
            'hashed' => $hashed,
        ];
    }

    /**
     * Verify and consume a recovery code.
     */
    public function verifyRecoveryCode(TwoFactorAuthenticatable $user, string $code): bool
    {
        $storedCodes = json_decode($user->two_factor_recovery_codes, true);

        if (! is_array($storedCodes)) {
            return false;
        }

        foreach ($storedCodes as $index => $hashedCode) {
            if (Hash::check($code, $hashedCode)) {
                // Consume the code by removing it
                unset($storedCodes[$index]);
                $user->two_factor_recovery_codes = json_encode(array_values($storedCodes));
                $user->save();

                return true;
            }
        }

        return false;
    }
}
