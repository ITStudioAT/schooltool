<?php

namespace App\Http\Controllers\Admin\Tutoring;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PaginateResource;
use App\Models\TutoringOfferRequest;
use Illuminate\Http\Request;

class OfferRequestController extends Controller
{
    public function index(Request $request)
    {
        if (! $auth_user = $this->userHasRole(['admin', 'tutoring_admin', 'teacher'])) {
            abort(403, 'Sie haben keine Berechtigung');
        }

        $validated = $request->validate([
            'search_string' => ['nullable', 'string', 'max:255'],
            'select_status' => ['nullable', 'string', 'in:all,open,answered,archived'],
        ]);

        $search_string = $validated['search_string'] ?? null;
        $select_status = $validated['select_status'] ?? 'all';

        $query = TutoringOfferRequest::where('school_id', $auth_user->school_id)
            ->with([
                'from_user:id,last_name,first_name,email,schoolclass,is_active',
                'to_user:id,last_name,first_name,email,schoolclass,is_active',
                'offer:id,title,description',
                'offer.subject:id,short_name,long_name',
            ])
            ->latest('created_at');

        if ($search_string) {
            $query->where(function ($q) use ($search_string) {
                $q->where('message', 'like', "%{$search_string}%")
                    ->orWhereHas('from_user', function ($sub) use ($search_string) {
                        $sub->where('last_name', 'like', "%{$search_string}%")
                            ->orWhere('first_name', 'like', "%{$search_string}%")
                            ->orWhere('email', 'like', "%{$search_string}%")
                            ->orWhere('schoolclass', 'like', "%{$search_string}%");
                    })
                    ->orWhereHas('to_user', function ($sub) use ($search_string) {
                        $sub->where('last_name', 'like', "%{$search_string}%")
                            ->orWhere('first_name', 'like', "%{$search_string}%")
                            ->orWhere('email', 'like', "%{$search_string}%");
                    })
                    ->orWhereHas('offer', function ($sub) use ($search_string) {
                        $sub->where('title', 'like', "%{$search_string}%");
                    })
                    ->orWhereHas('offer.subject', function ($sub) use ($search_string) {
                        $sub->where('short_name', 'like', "%{$search_string}%")
                            ->orWhere('long_name', 'like', "%{$search_string}%");
                    });
            });
        }

        if ($select_status === 'open') {
            $query->whereNull('mail_at');
        } elseif ($select_status === 'answered') {
            $query->whereNotNull('mail_at')->whereNull('archived_at')->whereNull('to_user_archived_at');
        } elseif ($select_status === 'archived') {
            $query->where(function ($q) {
                $q->whereNotNull('archived_at')->orWhereNotNull('to_user_archived_at');
            });
        }

        $requests = $query->paginate(config('schooltool.pagination'));

        return response()->json([
            'data' => $requests->map(function (TutoringOfferRequest $r) {
                return [
                    'id' => $r->id,
                    'message' => $r->message,
                    'is_serious' => (bool) $r->is_serious,
                    'sent_at' => $r->sent_at,
                    'last_sent_at' => $r->last_sent_at,
                    'seen_at' => $r->seen_at,
                    'last_seen_at' => $r->last_seen_at,
                    'sent_count' => $r->sent_count,
                    'seen_count' => $r->seen_count,
                    'mail_at' => $r->mail_at,
                    'archived_at' => $r->archived_at,
                    'to_user_archived_at' => $r->to_user_archived_at,
                    'created_at' => $r->created_at,
                    'from_user' => $r->from_user ? [
                        'id' => $r->from_user->id,
                        'last_name' => $r->from_user->last_name,
                        'first_name' => $r->from_user->first_name,
                        'email' => $r->from_user->email,
                        'schoolclass' => $r->from_user->schoolclass,
                        'is_active' => (bool) $r->from_user->is_active,
                    ] : null,
                    'to_user' => $r->to_user ? [
                        'id' => $r->to_user->id,
                        'last_name' => $r->to_user->last_name,
                        'first_name' => $r->to_user->first_name,
                        'email' => $r->to_user->email,
                        'schoolclass' => $r->to_user->schoolclass,
                        'is_active' => (bool) $r->to_user->is_active,
                    ] : null,
                    'offer' => $r->offer ? [
                        'id' => $r->offer->id,
                        'title' => $r->offer->title,
                        'description' => $r->offer->description,
                        'subject' => $r->offer->subject ? [
                            'short_name' => $r->offer->subject->short_name,
                            'long_name' => $r->offer->subject->long_name,
                        ] : null,
                    ] : null,
                ];
            }),
            'meta' => new PaginateResource($requests),
        ]);
    }
}
