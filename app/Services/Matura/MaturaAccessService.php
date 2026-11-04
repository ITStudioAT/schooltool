<?php

namespace App\Services\Matura;

use App\Models\MaturaAccess;
use App\Models\MaturaSession;
use App\Models\User;
use Illuminate\Http\Request;

class MaturaAccessService
{
    public function admin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active && $user->hasAdminShellAccess(), 403);

        return $user;
    }

    public function canManage(MaturaSession $session, User $user): bool
    {
        return (int) $session->school_id === (int) $user->school_id
            && $user->is_active && $user->hasAdminShellAccess()
            && ((int) $session->created_by === (int) $user->id || $user->hasAnyRole(['admin', 'super_admin']));
    }

    public function manager(Request $request, MaturaSession $session): User
    {
        $user = $this->admin($request);
        abort_unless((int) $session->school_id === (int) $user->school_id, 404);
        abort_unless($this->canManage($session, $user), 403);

        return $user;
    }

    /** @return array{manager: bool, name: string, access_id: ?int, room_id: ?int} */
    public function actor(Request $request, MaturaSession $session, bool $guest = false): array
    {
        if (! $guest) {
            $user = $this->admin($request);
            abort_unless((int) $session->school_id === (int) $user->school_id, 404);
            if ($this->canManage($session, $user)) {
                return ['manager' => true, 'name' => trim($user->first_name.' '.$user->last_name), 'access_id' => null, 'room_id' => null];
            }
            $access = $session->accesses()->where('user_id', $user->id)
                ->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        } else {
            $access = $session->accesses()->find($request->session()->get('matura_access.id'));
            abort_unless($access && $access->user_id === null && hash_equals(
                (string) $access->token_hash,
                (string) $request->session()->get('matura_access.fingerprint')
            ), 401, 'Bitte erneut mit Ihrem Zugang anmelden.');
        }
        abort_unless($access?->isValid() && $session->status !== 'closed', 403, 'Dieser Zugang ist abgelaufen oder widerrufen.');

        return ['manager' => false, 'name' => $access->name, 'access_id' => $access->id, 'room_id' => $access->matura_room_id];
    }

    public function guestSession(Request $request): MaturaSession
    {
        $access = MaturaAccess::query()->find($request->session()->get('matura_access.id'));
        abort_unless($access, 401, 'Bitte mit Ihrem persönlichen Zugang anmelden.');

        return $access->session;
    }

    /** @param array{manager: bool, name: string, access_id: ?int, room_id: ?int} $actor */
    public function assertStation(MaturaSession $session, array $actor, ?int $roomId): void
    {
        if ($actor['manager']) {
            return;
        }
        abort_unless($actor['room_id'] === $roomId, 403, 'Diese Aktion gehört zu einer anderen Station.');
        $current = $roomId === null
            ? $session->station_access_id
            : $session->rooms()->findOrFail($roomId)->supervisor_access_id;
        abort_unless((int) $current === $actor['access_id'], 409, 'Bitte zuerst die Aufsicht übernehmen. Die Station wurde möglicherweise übergeben.');
    }
}
