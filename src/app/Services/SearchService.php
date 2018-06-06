<?php

namespace App\Services;

use App\Repositories\SearchRepository;
use function foo\func;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;

use App\Merchant;
use App\Repositories\EsSearchLogRepository;

class SearchService
{
	static private $client = null;
    protected $errors = [];

    protected $repo;

    public function __construct( \App\Repositories\EsSearchRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

	public function setError($error)
    {
        $this->errors[] = $error;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getSearchRepository()
    {
        return $this->repo;
    }
    /**
     * Get auto complete suggestion
     *
     * @author Naresh Maharjan <naresh.maharjan@cashrewards.com>
     * @param string $query
     * @return array
     */
    public function autoComplete(string $query): array
    {
        return $this->repo->autoComplete($query);
    }

    /**
     * Search Merchants in Elasticsearch via Search Repository
     *
     * @author Naresh Maharjan <naresh.maharjan@cashrewards.com>
     * @param array $searchOptions
     * @param array $options
     * @return array|mixed
     */
    public function searchRepo(array $searchOptions, array $options)
    {
        //TODO: needs refactoring
        
        $redisCache = Cache::get('search:'.json_encode($searchOptions));
        if ( !$redisCache || !empty($options['no-cache']) )
        {
            /** @var Collection $merchantsList */

            $find_params = $searchOptions;
            $find_params['keywords'] = Merchant::purifyString($searchOptions['query']);
            $perPage = $searchOptions['per_page'];
            $page = $searchOptions['page'];

            $size = $perPage ?? 10;
            $from = !empty($page) ? ($page - 1) * $perPage : 0;
            //$searchQuery = $this->getSearchQuery($find_params['keywords'], $size, $from);
            $datetime = $options['query_time'];


            $searchQuery = $this->repo->getSearchQuery(['query_term' => $find_params['keywords'], 'query_datetime' => $datetime], $size, $from);
            $result = $this->repo->searchData($searchQuery);

            if ($result === null || $result->isEmpty()) {
                return null;
            }

            $merchantsList = new Collection();
            if (isset($result['hits']) && isset($result['hits']['hits'])) {
                $hits = $result['hits']['hits'];
                foreach($hits as $hit) {
                    $highlight = !empty($hit['highlight']) ? $hit['highlight'] : [];
                    $model = new Merchant();
                    $model->setRawAttributes($hit['_source']);
                    $model->setAttribute('id', $hit['_id']);
                    $model->setAttribute('weight', $model->calculateWeight($find_params['keywords'], $highlight));
                    $model->setAttribute('relevance', $hit['_score']);
                    $model->setAttribute('client_id', $hit['_source']['domain']);
                    $model->setAttribute('store_url', '/' . $hit['_source']['hyphenatedstring']);
                    if (isset($searchOptions['fields'])) {
                        $model->setVisible($searchOptions['fields']);
                    }
                    $merchantsList->push($model);
                }

                $response = [
                    'summary' => [
                        'q' => $searchOptions['query'],
                        'query_time' => $options['query_time'],
                        'total' => (int) $result['hits']['total'],
                        'count' => count($merchantsList),
                        'per_page' => (int) $perPage,
                        'page' => (int) $page,
                    ],
                    'items' => $merchantsList->values()->all(),
                    'merchants_ids' => $merchantsList->map(function($item) {
                        return [
                            'merchantid' => $item['merchantid'],
                            'merchantname' => $item['merchantname'],
                            'search_strategy' => $item['search_strategy']
                        ];
                    })->toArray(),
                    'cache' => [
                        'cache_timestamp' => Carbon::now()->timestamp,
                    ]
                ];
                $cacheTime = (int) env('CACHE_TIME',10);
                Cache::put('search:'.json_encode($searchOptions), json_encode($response), $cacheTime);
            } else {
                return null;
            }
        } else {
            $response = json_decode($redisCache,true);
            $merchantsList = new Collection();
            foreach ($response['items'] as $item) {
                $merchant = new Merchant();
                $merchant->setRawAttributes($item);
                if (isset($searchOptions['fields'])) {
                    $merchant->setVisible($searchOptions['fields']);
                }
                $merchantsList->push($merchant);
            }
        }

        $merchantsList = Merchant::sortByStrategy($options['sort'], $merchantsList, $detail);

        $cache_age = $this->getTimeDiff($response['cache']['cache_timestamp'], Carbon::now()->timestamp);

        $response['summary']['sort'] = $detail['sort'];
        $response['profile']['es_elasped_msec'] = !empty($result['took']) ? $result['took'] : 0;
        $response['cache']['cache_time'] = Carbon::createFromTimeStamp($response['cache']['cache_timestamp'])->timezone('Australia/Sydney')->format('Y-m-d H:i:s e');
        $response['cache']['cache_age'] = $cache_age;
        $response['items'] = $this->sortMerchantList($merchantsList);
        return $response;
    }

    public function getTimeDiff($left, $right)
    {
        $leftTime = Carbon::createFromTimeStamp($left);
        $rightTime = Carbon::createFromTimeStamp($right);
        $mins = $leftTime->diffForHumans($rightTime);
        return $mins;
    }

    public function createSearchSortingScript()
    {
        $painless_script = "
            String merchantName = '';
            String keywords = '';
            String descriptionLong = '';
            if(params._source.merchantname != null){
                merchantName = params._source.merchantname.toLowerCase();
            }
            if(params._source.keywords != null){
                keywords = params._source.keywords.toLowerCase();
            }
            if(params._source.descriptionLong != null){
                descriptionLong = params._source.descriptionLong.toLowerCase();
            }
            int weight = 0;
            int clickouts = params._source.clickouts;
            String search_term = params.search_term;
            
            if(merchantName == search_term ){
                weight = 100;
            } else if(merchantName.indexOf(search_term) == 0){
                weight= 80;
            } else if(merchantName.indexOf(search_term) > 0){
                weight = 60;
            } else if(keywords.indexOf(search_term) > 0){
                weight = 45;
            } else if(descriptionLong.indexOf(search_term) > 0){
                weight = 1;
            }
            if(clickouts>0){
                return (weight + Math.log(clickouts));
            }else{
                return weight;
            }
            ";

        $minifier = new \MatthiasMullie\Minify\JS($painless_script);
        $painless_script = $minifier->minify();
        $stored_script =
            '{
               "script": {
                    "lang" : "painless",
                    "code" : %painless_script%
                }          
            }';
        $stored_script = str_replace('%painless_script%', json_encode($painless_script), $stored_script);
        // ['body' => $stored_script];


        $params = [
            'id' => 'merchant-search-sort',
            'body' => $stored_script,
        ];

        if ( $this->existScript($params) )
        {
            return false;
        }

        return $this->putScript($params);
    }

    public function existScript($params)
    {
        try {
            $res = $this->getScript($params);
        }
        catch( \Elasticsearch\Common\Exceptions\Missing404Exception $ex) {
            $res = json_decode($ex->getMessage(), true);
        }

        return (isset($res['found'])) ? $res['found'] : null;
    }

    public function getScript($params)
    {
        // TODO: need to organize code
        $repo = new \App\Repositories\SearchRepository();
        $client = $repo->getClient();

        $id = $client->extractArgument($params, 'id');

        $promise = $client->transport->performRequest(
            'GET',
            "/_scripts/$id",
            null,
            null,
            []
        );

        return $client->transport->resultOrFuture($promise);
    }

    public function putScript($params)
    {
        // TODO: need to organize code
        $repo = new \App\Repositories\SearchRepository();
        $client = $repo->getClient();

        $id = $client->extractArgument($params, 'id');
        $body = $client->extractArgument($params, 'body');

        $promise = $client->transport->performRequest(
            'POST',
            "/_scripts/$id",
            null,
            $body,
            []
        );

        return $client->transport->resultOrFuture($promise);
    }


    /**
     * Sort merchants list by clickouts if weights are same
     *
     * @author Naresh Maharjan <naresh.maharjan@cashrewads.com>
     * @param Collection $merchants
     * @return array
     */
    public function sortMerchantList(Collection $merchants)
    {
        $finalList = [];
        $merchants = $merchants->values()->groupBy('weight')->toArray();
        krsort($merchants);
        collect($merchants)->each(function ($val) use (&$finalList){
            collect($val)->values()->sortByDesc('clickouts')->values()->each(function ($value) use (&$finalList) {
                $finalList[] = $value;
            });
        });
        return $finalList;
    }
}
