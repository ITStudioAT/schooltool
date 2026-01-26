<?php

namespace App\Jobs\Teaching;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Events\Import116FinishedEvent;

class Import116 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public $user, public string $path)
    {
        // placeholder for future payload
    }

    public function handle(): void
    {
        // TODO: implement import
        broadcast(new Import116FinishedEvent(
            200,
            $this->user->id,
            'Import 116 wurde abgeschlossen.',
            ['path' => $this->path]
        ));
    }
}
