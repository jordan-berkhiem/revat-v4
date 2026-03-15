<?php

namespace App\Contracts;

use Carbon\Carbon;

interface TwoFactorAuthenticatable
{
    public function getTwoFactorSecret(): ?string;

    public function setTwoFactorSecret(?string $secret): void;

    public function getTwoFactorConfirmedAt(): ?Carbon;

    public function setTwoFactorConfirmedAt(?Carbon $timestamp): void;

    public function hasTwoFactorEnabled(): bool;

    public function getTwoFactorLabel(): string;
}
