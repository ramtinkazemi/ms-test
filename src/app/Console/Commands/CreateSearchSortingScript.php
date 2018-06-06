<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Services\SearchService;

class CreateSearchSortingScript extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'searchms:create_search_sorting_script';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $service;
    /**
     * Create a new command instance.
     * @param SearchService $service
     */
    public function __construct(SearchService $service)
    {
        parent::__construct();

        $this->service = $service;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $res = $this->service->createSearchSortingScript();
        if($res === false) {
            $this->info('Stored Script is already exist');
            return;
        }

        $this->info(print_r($res, true));
    }
}
