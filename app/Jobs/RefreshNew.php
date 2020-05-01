<?php

namespace App\Jobs;

use App\Jobs\Job;
use Artisan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshNew extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Artisan::call('airflix:genres');
        Artisan::call('airflix:movies', [
            '--new' => true,
        ]);
        Artisan::call('airflix:shows', [
            '--new' => true,
        ]);
    }
}
