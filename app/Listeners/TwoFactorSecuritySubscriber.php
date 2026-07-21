<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

class TwoFactorSecuritySubscriber
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(TwoFactorAuthenticationEnabled::class, [$this, 'setupStarted']);
        $events->listen(TwoFactorAuthenticationConfirmed::class, [$this, 'enabled']);
        $events->listen(TwoFactorAuthenticationDisabled::class, [$this, 'disabled']);
        $events->listen(RecoveryCodesGenerated::class, [$this, 'recoveryCodesRegenerated']);
        $events->listen(RecoveryCodeReplaced::class, [$this, 'recoveryCodeUsed']);
        $events->listen(TwoFactorAuthenticationFailed::class, [$this, 'challengeFailed']);
    }

    public function setupStarted(TwoFactorAuthenticationEnabled $event): void
    {
        $this->write('info', 'two_factor_setup_started', $event->user);
    }

    public function enabled(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->write('info', 'two_factor_enabled', $event->user);
    }

    public function disabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->write('info', 'two_factor_disabled_or_setup_cancelled', $event->user);
    }

    public function recoveryCodesRegenerated(RecoveryCodesGenerated $event): void
    {
        $this->write('info', 'two_factor_recovery_codes_regenerated', $event->user);
    }

    public function recoveryCodeUsed(RecoveryCodeReplaced $event): void
    {
        $this->write('info', 'two_factor_recovery_code_login_succeeded', $event->user);
    }

    public function challengeFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->write('warning', 'two_factor_challenge_failed', $event->user);
    }

    private function write(string $level, string $securityEvent, mixed $user): void
    {
        Log::log($level, 'Security event', [
            'security_event' => $securityEvent,
            'user_id' => $user instanceof User ? $user->id : null,
            'school_id' => $user instanceof User ? $user->school_id : null,
            'ip' => request()->ip(),
        ]);
    }
}
