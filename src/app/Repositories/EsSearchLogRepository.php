<?php

namespace App\Repositories;

use Carbon\Carbon;

use Cashrewards\Elasticsearch\Repositories\ElasticSearchRepository;

class EsSearchLogRepository extends ElasticSearchRepository
{
    protected $index = 'searchms-searchlog';
    protected $index_type = 'searchms-searchlog';
    protected $index_mapping;

    public function __construct()
    {
        $this->index_mapping = json_decode(file_get_contents(__DIR__ . '/EsSearchLogMapping.json'), TRUE);

        parent::__construct();
    }
}