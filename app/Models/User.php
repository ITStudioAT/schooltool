<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use App\Notifications\StandardEmail;
use App\Traits\UserTrait;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;
    use UserTrait;
    use HasApiTokens;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    protected $guard_name = 'web';

    protected $guarded = [];
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
            'token_2fa_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function shouldDelete(): bool
    {

        if (count($this->roles) > 0) abort(403, "Benutzer kann nicht gelöscht werden, da er noch Rollen inne hat.");
        $this->delete();
        return true;
    }

    public function sendVerificationEmail()
    {
        $uuid = $this->generateUuid();

        $data = [
            'from_address' => env('MAIL_FROM_ADDRESS'),
            'from_name' => env('MAIL_FROM_NAME'),
            'subject' => 'E-Mail-Verifikation',
            'markdown' => 'spa::mails.admin.sendEmailVerification',
            'url' => $data['url'] = config('app.url') . '/admin/email_verification?email=' . $this->email . '&uuid=' . $uuid,
            'token-expire-time' => config('spa.token_expire_time'),
        ];

        Notification::route('mail', $this->email)->notify(new StandardEmail($data));
    }


    public function sendConfirmEmail()
    {
        // XXXXXXXXXXXXXXXXXXXXX
        $uuid = $this->generateUuid();

        $data = [
            'from_address' => env('MAIL_FROM_ADDRESS'),
            'from_name' => env('MAIL_FROM_NAME'),
            'subject' => 'E-Mail-Verifikation',
            'markdown' => 'spa::mails.admin.sendEmailVerification',
            'url' => $data['url'] = config('app.url') . '/admin/email_verification?email=' . $this->email . '&uuid=' . $uuid,
            'token-expire-time' => config('spa.token_expire_time'),
        ];

        Notification::route('mail', $this->email)->notify(new StandardEmail($data));
    }

    public function generateUuid(): string
    {
        $this->uuid = Str::uuid();
        $this->uuid_at = now();
        $this->save();

        return $this->uuid;
    }

    public function checkUuid($uuid): bool
    {
        if ($this->uuid !== $uuid || ! $this->uuid_at) {
            return false;
        }

        return $this->uuid_at > now()->subMinutes(config('spa.token_expire_time', 120));
    }

    public function emailVerified(): bool
    {
        $this->email_verified_at = now();
        $this->uuid = null;
        $this->uuid_at = null;
        $this->save();

        return true;
    }
}
