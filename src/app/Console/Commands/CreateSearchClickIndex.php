<?php

namespace App\Console\Commands;

use App\Repositories\SearchClickRepository;
use Illuminate\Console\Command;

class CreateSearchClickIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'searchms:create_search_click_index';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create search click index';


    /**
     * Index service object
     *
     * @var SearchClickRepository
     */
    protected $searchClickRepository;

    /**
     * Create a new command instance.
     *
     * @param SearchClickRepository $searchClickRepository
     */
    public function __construct(SearchClickRepository $searchClickRepository)
    {
        $this->searchClickRepository = $searchClickRepository;

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): void
    {
        if ($this->searchClickRepository->setIndices()->createIndex()) {
            $this->line('Index created successfully');
        } else {
            $this->line('Could not process task');
        }
    }
}
