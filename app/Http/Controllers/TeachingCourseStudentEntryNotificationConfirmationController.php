<?php

namespace App\Http\Controllers;

use App\Models\TeachingCourseStudentEntryNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

class TeachingCourseStudentEntryNotificationConfirmationController extends Controller
{
    public function open(TeachingCourseStudentEntryNotification $notification): Response
    {
        if (! $notification->opened_at) {
            $notification->forceFill(['opened_at' => now()])->save();
        }

        return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=='))
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function show(TeachingCourseStudentEntryNotification $notification): View
    {
        if ($notification->confirmed_at) {
            return $this->confirmationView($notification);
        }

        return view('homepage.restaurant-approval-response', [
            'title' => 'Empfang bestätigen',
            'subtitle' => $notification->recipient_label,
            'text' => 'Bitte bestätigen Sie, dass Sie diese Verständigung erhalten haben.',
            'status' => 'BESTÄTIGUNG ERFORDERLICH',
            'form_url' => URL::signedRoute('teaching-entry-notifications.confirm.store', [
                'notification' => $notification,
            ]),
            'button_label' => 'Empfang bestätigen',
            'back_url' => null,
        ]);
    }

    public function store(TeachingCourseStudentEntryNotification $notification): View
    {
        if (! $notification->confirmed_at) {
            $notification->forceFill([
                'confirmed_at' => now(),
                'confirmation_method' => 'email',
                'confirmed_by_user_id' => null,
                'confirmed_by_label' => $notification->recipient_label,
            ])->save();
        }

        return $this->confirmationView($notification->fresh());
    }

    private function confirmationView(TeachingCourseStudentEntryNotification $notification): View
    {
        return view('homepage.restaurant-approval-response', [
            'title' => 'Empfang bestätigt',
            'subtitle' => $notification->recipient_label,
            'text' => 'Vielen Dank. Ihre Bestätigung wurde am '.$notification->confirmed_at?->format('d.m.Y, H:i').' Uhr gespeichert.',
            'status' => 'BESTÄTIGT',
            'form_url' => null,
            'button_label' => null,
            'back_url' => null,
        ]);
    }
}
