<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_VP_ADMIN = 'vp_admin';
    public const ROLE_SEMI_ADMIN = 'semiadmin';
    public const ROLE_STAFF = 'staff';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'birthdate',
        'address',
        'role',
        'email',
        'phone',
        'password',
        'password_changed_at',
        'last_auto_backup_downloaded_on',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_auto_backup_downloaded_on' => 'date',
            'password' => 'hashed',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function knownRoles(): array
    {
        return [
            self::ROLE_VP_ADMIN,
            self::ROLE_SEMI_ADMIN,
            self::ROLE_STAFF,
        ];
    }

    public function hasKnownRole(): bool
    {
        return in_array($this->role, self::knownRoles(), true);
    }

    public function dashboardRouteName(): string
    {
        return match ($this->role) {
            self::ROLE_VP_ADMIN => 'dashboard.admin',
            self::ROLE_SEMI_ADMIN => 'dashboard.semi-admin',
            self::ROLE_STAFF => 'staff.pump.index',
            default => 'login',
        };
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_VP_ADMIN => 'Admin',
            self::ROLE_SEMI_ADMIN => 'Semi Admin',
            self::ROLE_STAFF => 'Staff',
            default => 'Unknown Role',
        };
    }
}
