<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class TeachingBackfillSchoolyearSettingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'teaching:backfill-schoolyear-settings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill schoolyear-scoped teaching behaviour and notification settings from legacy user fields';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $processedUsers = 0;
        $updatedUsers = 0;
        $backfilledBehaviourUsers = 0;
        $backfilledNotificationUsers = 0;

        User::query()
            ->whereNotNull('schoolyear_id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$processedUsers, &$updatedUsers, &$backfilledBehaviourUsers, &$backfilledNotificationUsers): void {
                foreach ($users as $user) {
                    $processedUsers++;

                    $schoolyearKey = (string) $user->schoolyear_id;
                    $behaviourBackfilled = false;
                    $notificationBackfilled = false;

                    $behaviourBySchoolyear = is_array($user->teaching_behaviour_by_schoolyear) ? $user->teaching_behaviour_by_schoolyear : [];
                    $legacyBehaviour = is_array($user->teaching_behaviour) ? array_values($user->teaching_behaviour) : [];
                    if ($legacyBehaviour !== [] && ! is_array($behaviourBySchoolyear[$schoolyearKey] ?? null)) {
                        $behaviourBySchoolyear[$schoolyearKey] = $legacyBehaviour;
                        $user->teaching_behaviour_by_schoolyear = $behaviourBySchoolyear;
                        $behaviourBackfilled = true;
                    }

                    $notificationsBySchoolyear = is_array($user->teaching_notifications_by_schoolyear) ? $user->teaching_notifications_by_schoolyear : [];
                    $legacyNotifications = is_array($user->teaching_notifications) ? array_values($user->teaching_notifications) : [];
                    if ($legacyNotifications !== [] && ! is_array($notificationsBySchoolyear[$schoolyearKey] ?? null)) {
                        $notificationsBySchoolyear[$schoolyearKey] = $legacyNotifications;
                        $user->teaching_notifications_by_schoolyear = $notificationsBySchoolyear;
                        $notificationBackfilled = true;
                    }

                    if (! $behaviourBackfilled && ! $notificationBackfilled) {
                        continue;
                    }

                    $user->save();
                    $updatedUsers++;

                    if ($behaviourBackfilled) {
                        $backfilledBehaviourUsers++;
                    }

                    if ($notificationBackfilled) {
                        $backfilledNotificationUsers++;
                    }
                }
            });

        $this->info("Processed {$processedUsers} user(s).");
        $this->info("Updated {$updatedUsers} user(s).");
        $this->info("Backfilled behaviour settings for {$backfilledBehaviourUsers} user(s).");
        $this->info("Backfilled notification settings for {$backfilledNotificationUsers} user(s).");

        return self::SUCCESS;
    }
}
