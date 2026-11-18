<?php

namespace App\Models;

use App\Enums\RoleName;
use App\Enums\UserType;
use App\Models\Concerns\HasTwoFactorAuthentication;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, HasRoles, HasTwoFactorAuthentication, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'type',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'type' => UserType::class,
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->hasAnyRole(
                array_map(
                    static fn (RoleName $role): string => $role->value,
                    RoleName::adminPanelRoles(),
                ),
            ),
            'teacher' => $this->hasRole(RoleName::Teacher->value)
                || $this->type === UserType::Teacher
                || (bool) $this->teacher,
            'student' => $this->hasRole(RoleName::Student->value)
                || $this->type === UserType::Student
                || (bool) $this->student
                || $this->hasRole(RoleName::Guardian->value)
                || $this->type === UserType::Guardian
                || (bool) $this->guardian,
            default => false,
        };
    }
}

