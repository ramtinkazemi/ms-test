<?php
declare(strict_types=1);

namespace App\Repositories;

use Cashrewards\Elasticsearch\Repositories\ElasticSearchRepository;


/**
 * Search Click Repository
 *
 * @package App\Repositories
 * @author Naresh Maharjan <naresh.maharjan@cashrewards.com
 */
class SearchClickRepository extends ElasticSearchRepository
{


    /**
     * Elastic search index
     *
     * @var string
     */
    protected $index;

    /**
     * Elastic search index type
     *
     * @var string
     */
    protected $index_type;


    /**
     * Elastic search index mapping
     *
     * @var mixed
     */
    protected $index_mapping;


    /**
     * SearchLogRepository constructor.
     */
    public function __construct()
    {
        $this->index_mapping = json_decode(file_get_contents(__DIR__ . '/SearchClickMapping.json'), TRUE);
        parent::__construct();
    }


    /**
     * Set elastic search indices
     *
     * @return $this
     */
    public function setIndices()
    {
        $this->index = 'searchms-search-result-click';
        $this->index_type = 'searchms-search-result-click';
        return $this;
    }


}