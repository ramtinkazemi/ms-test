<?php
declare(strict_types=1);

namespace App\Repositories;


use Illuminate\Support\Collection;

/**
 * Trait ElasticSearchUtils
 *
 * @package App\Repositories
 * @author Naresh Maharjan <naresh.maharjan@cahsrewards.com>
 */
trait ElasticSearchUtils
{


    /**
     * Search in elasticsearch
     *
     * @param array $params
     * @return Collection
     */
    public function searchEs(array $params = []): Collection
    {

        if (!array_key_exists('index', $params)) {
            $params['index'] = $this->index;
        }
        if (!array_key_exists('type', $params)) {
            $params['type'] = $this->index_type;
        }
        return collect($this->getClient()->search($params));
    }
}