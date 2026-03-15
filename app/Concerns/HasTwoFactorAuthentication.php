<?php

namespace App\Concerns;

use Carbon\Carbon;

trait HasTwoFactorAuthentication
{
    public function getTwoFactorSecret(): ?string
    {
        return $this->two_factor_secret;
    }

    public function setTwoFactorSecret(?string $secret): void
    {
        $this->two_factor_secret = $secret;
        $this->save();
    }

    public function getTwoFactorConfirmedAt(): ?Carbon
    {
        return $this->two_factor_confirmed_at;
    }

    public function setTwoFactorConfirmedAt(?Carbon $timestamp): void
    {
        $this->two_factor_confirmed_at = $timestamp;
        $this->save();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function getTwoFactorLabel(): string
    {
        return $this->email;
    }
}
