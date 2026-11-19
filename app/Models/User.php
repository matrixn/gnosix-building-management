<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'registration_source'])]
class User extends Authenticatable implements MustVerifyEmail, HasLocalePreference
{
    protected $attributes = ['locale' => 'ro', 'auth_version' => 1];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'archived_at' => 'datetime',
            'registration_source' => 'array',
            'password' => 'hashed',
            'first_signed_in_at' => 'datetime',
            'last_signed_in_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'auth_version' => 'integer',
        ];
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function preferredLocale(): string
    {
        return in_array($this->locale, ['ro', 'en'], true) ? $this->locale : 'ro';
    }

    public function memberships()
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'organization_memberships')->wherePivotIn('role', OrganizationMembership::ROLES)->wherePivotIn('access_scope', ['all', 'properties'])->withPivot(['role', 'access_scope'])->withTimestamps();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\CustomerVerifyEmail);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\CustomerResetPassword($token));
    }
}
