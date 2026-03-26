<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RestaurantCdgymLunchUserSepaSyncService
{
    /**
     * @param  Collection<int, array<string, mixed>|object>  $sourceRows
     * @return array<string, int>
     */
    public function sync(int $schoolId, Collection $sourceRows, bool $apply = false): array
    {
        $summary = [
            'source_rows_seen' => $sourceRows->count(),
            'source_users_seen' => 0,
            'source_users_skipped' => 0,
            'local_users_matched' => 0,
            'local_users_missing' => 0,
            'sepa_true_seen' => 0,
            'sepa_false_seen' => 0,
            'users_to_mark_sepa' => 0,
            'users_to_clear_sepa' => 0,
            'users_marked_sepa' => 0,
            'users_cleared_sepa' => 0,
        ];

        $normalizedSourceUsers = $this->normalizedSourceUsers($sourceRows, $summary);
        $summary['source_users_seen'] = $normalizedSourceUsers->count();

        foreach ($normalizedSourceUsers as $sourceUser) {
            if ($sourceUser['has_sepa']) {
                $summary['sepa_true_seen']++;
            } else {
                $summary['sepa_false_seen']++;
            }

            $localUser = User::query()
                ->where('school_id', $schoolId)
                ->whereRaw('LOWER(email) = ?', [$sourceUser['email']])
                ->whereHas('roles', function ($query): void {
                    $query->where('name', 'lunch_user');
                })
                ->first();

            if (! $localUser) {
                $summary['local_users_missing']++;

                continue;
            }

            $summary['local_users_matched']++;

            if ($sourceUser['has_sepa']) {
                if (! $localUser->sepa_at) {
                    $summary['users_to_mark_sepa']++;

                    if ($apply) {
                        $localUser->sepa_at = now();
                        $localUser->save();
                        $summary['users_marked_sepa']++;
                    }
                }

                continue;
            }

            if ($localUser->sepa_at) {
                $summary['users_to_clear_sepa']++;

                if ($apply) {
                    $localUser->sepa_at = null;
                    $localUser->save();
                    $summary['users_cleared_sepa']++;
                }
            }
        }

        return $summary;
    }

    /**
     * @param  Collection<int, array<string, mixed>|object>  $sourceRows
     * @param  array<string, int>  $summary
     * @return Collection<int, array{email: string, has_sepa: bool}>
     */
    private function normalizedSourceUsers(Collection $sourceRows, array &$summary): Collection
    {
        /** @var array<string, array{email: string, has_sepa: bool}> $groupedUsers */
        $groupedUsers = [];

        foreach ($sourceRows as $sourceRow) {
            $normalizedRow = $this->normalizeSourceRow($sourceRow);

            if (! $normalizedRow) {
                $summary['source_users_skipped']++;

                continue;
            }

            $email = $normalizedRow['email'];

            if (! array_key_exists($email, $groupedUsers)) {
                $groupedUsers[$email] = $normalizedRow;

                continue;
            }

            $groupedUsers[$email] = [
                'email' => $email,
                'has_sepa' => $groupedUsers[$email]['has_sepa'] || $normalizedRow['has_sepa'],
            ];
        }

        return collect(array_values($groupedUsers));
    }

    /**
     * @param  array<string, mixed>|object  $sourceRow
     * @return array{email: string, has_sepa: bool}|null
     */
    private function normalizeSourceRow(array|object $sourceRow): ?array
    {
        $email = Str::lower(trim((string) data_get($sourceRow, 'email')));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return [
            'email' => $email,
            'has_sepa' => (bool) data_get($sourceRow, 'sepa'),
        ];
    }
}
