<?php

namespace App\Jobs;

use App\Models\Register;
use App\Notifications\StandardEmail;
use App\Services\PrintRegisterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class PrintRegisterSupervisorJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public $user, public $data)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $service = new PrintRegisterService;
        $path = $service->printSupervisor($this->user, $this->data);

        $school = $this->user->selectedSchool;
        $register = Register::findOrFail($this->data['register_id']);

        $email = [
            'from_address' => config('schooltool.noreply_email'),
            'from_name' => $school['long_name'],
            'logo' => asset('/storage/images/'.$school['logo']),
            'subject' => $register->name.': Pdf-Datei (Betreuer)',
            'markdown' => 'mails.admin.sendPrint',
        ];

        Notification::route('mail', $this->user->email)
            ->notify(new StandardEmail(
                $email,
                [
                    $path,
                ]
            ));
    }
}
