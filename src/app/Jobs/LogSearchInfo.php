<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

use App\Services\SearchLogService;

class LogSearchInfo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $logData;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $logData)
    {
        $this->logData = $logData;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(SearchLogService $service)
    {
        $url = isset($this->logData['full_url'])? $this->logData['full_url'] : '';
        Log::debug(__CLASS__.':'."Processing [$url]" );
        $res = $service->saveSearchLog($this->logData);

    }
}
