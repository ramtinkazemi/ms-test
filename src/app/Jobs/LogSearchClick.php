<?php

namespace App\Jobs;

use App\Facades\SearchClickLog;
use App\Repositories\SearchClickRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class LogSearchClick implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var SearchClickRepository $searchClickRepository
     */
    protected  $searchClickRepository;

    /**
     * @var array $document
     */
    protected $document;

    /**
     * Create a new job instance.
     * @param SearchClickRepository $searchClickRepository
     * @param array $document
     */
    public function __construct(SearchClickRepository $searchClickRepository, array $document)
    {
        $this->searchClickRepository = $searchClickRepository;
        $this->document = $document;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //Log search click data into log file
        SearchClickLog::log($this->document);

        //Log data into elasticsearch index
        $this->searchClickRepository->setIndices()->index($this->document);
    }
}
