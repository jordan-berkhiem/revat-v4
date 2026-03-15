<?php

namespace App\Models;

use App\Concerns\HasTwoFactorAuthentication;
use App\Contracts\TwoFactorAuthenticatable;
use App\Enums\SupportLevel;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable implements FilamentUser, TwoFactorAuthenticatable
{
    use HasFactory, HasTwoFactorAuthentication, Notifiable;

    protected $guard = 'admin';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'support_level',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'support_level' => SupportLevel::class,
        ];
    }

    // ── Filament ─────────────────────────────────────────────────────

    public function canAccessPanel(Panel $panel): bool
    {
        return ! $this->isDeactivated();
    }

    // ── Deactivation ─────────────────────────────────────────────────

    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    public function deactivate(): void
    {
        $this->deactivated_at = now();
        $this->save();
    }

    public function reactivate(): void
    {
        $this->deactivated_at = null;
        $this->save();
    }
}
