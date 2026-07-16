<?php

namespace App\Traits;

use Illuminate\Support\Facades\Hash;

trait UserTrait
{
    public function setToken2Fa($minutes, $select = 1): string
    {
        if ($select == 1) {
            $this->token_2fa = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $this->token_2fa_expires_at = now()->addMinutes($minutes);
            $this->save();

            return $this->token_2fa;
        } else {
            $this->token_2fa_2 = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $this->token_2fa_2_expires_at = now()->addMinutes($minutes);
            $this->save();

            return $this->token_2fa_2;
        }
    }

    public function checkToken2Fa($token_2fa): bool
    {
        return $this->token_2fa !== null
            && $this->token_2fa_expires_at !== null
            && hash_equals((string) $this->token_2fa, (string) $token_2fa)
            && $this->token_2fa_expires_at->isFuture();
    }

    public function checkToken2Fa_2($token_2fa_2): bool
    {
        return $this->token_2fa_2 !== null
            && $this->token_2fa_2_expires_at !== null
            && hash_equals((string) $this->token_2fa_2, (string) $token_2fa_2)
            && $this->token_2fa_2_expires_at->isFuture();
    }

    public function consumeToken2Fa($token): bool
    {
        $consumed = static::query()
            ->whereKey($this->getKey())
            ->where('token_2fa', (string) $token)
            ->where('token_2fa_expires_at', '>', now())
            ->update([
                'token_2fa' => null,
                'token_2fa_expires_at' => null,
            ]);

        if ($consumed === 1) {
            $this->forceFill([
                'token_2fa' => null,
                'token_2fa_expires_at' => null,
            ]);
        }

        return $consumed === 1;
    }

    public function consumeToken2Fa2($token): bool
    {
        $consumed = static::query()
            ->whereKey($this->getKey())
            ->where('token_2fa_2', (string) $token)
            ->where('token_2fa_2_expires_at', '>', now())
            ->update([
                'token_2fa_2' => null,
                'token_2fa_2_expires_at' => null,
            ]);

        if ($consumed === 1) {
            $this->forceFill([
                'token_2fa_2' => null,
                'token_2fa_2_expires_at' => null,
            ]);
        }

        return $consumed === 1;
    }

    public function rememberLogin()
    {
        $this->login_at = now();
        $this->login_ip = request()->ip();
        $this->save();
    }

    public function setPassword($password)
    {
        $this->password = Hash::make($password);
        $this->save();
    }
}
