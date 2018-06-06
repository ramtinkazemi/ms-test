<?php
declare(strict_types=1);

namespace App\Repositories;


use Cashrewards\Elasticsearch\Repositories\ElasticSearchRepository;
use Illuminate\Support\Collection;

/**
 * Search Repository
 *
 * @package App\Repositories
 * @author Naresh Maharjan <naresh.maharjan@cashrewards.com>
 */
class SearchRepositoryV2 extends ElasticSearchRepository implements EsSearchRepositoryInterface
{
    use ElasticSearchUtils;

    /**
     * Elastic search index
     *
     * @var string
     */
    protected $index = 'logstash-cr-db-merchants-search-public-v2';

    public function __construct()
    {
        if (! empty(env('SEARCH_ES_HOST'))) {
            $this->hosts = [env('SEARCH_ES_HOST')];
        }
    }



    public function searchData(array $params = []): Collection
    {
        $res = $this->searchEs($params)->toArray();
          $tiers = [];

        array_walk(
            $res['aggregations']['merchant_searchs']['buckets'],
            function($item, $key) use (&$tiers){
                $tier = null;
                if (isset($item['merchant_tiers']['date_filtered']['top-cashbackrates']['hits']['hits'][0])) {
                    $tier_doc = $item['merchant_tiers']['date_filtered']['top-cashbackrates']['hits']['hits'][0];
                    unset($tier_doc['_source']['merchantid']);
                    unset($tier_doc['_source']['merchantaliasid']);
                    $tier = array_merge(['_tier_id' => $tier_doc['_id']], $tier_doc['_source']);
                }
                list($parent_type, $parent_id) = explode('#',$item['key']);
                $tiers[$parent_id] = $tier;
            }
        );

        $newHits = [];
        foreach($res['hits']['hits'] AS $k => $merchant) {

            $hit = $merchant;
            $hit_source = $merchant['_source'];
            if( isset($tiers[$merchant['_id']])) {
                $hit_source = array_merge( $hit_source, $tiers[$merchant['_id']]);
            }
            $hit['_source'] = $hit_source;
            $res['hits']['hits'][$k] = $hit;
        }

        unset($res['aggregations']);

        return collect($res);
    }


    /**
     * Generate search query
     *
     * @author Naresh Maharjan <naresh.maharjan@cashrewards.com>
     * @param array $queryParams
     * @param int $size
     * @param int $from
     * @return array
     */
    public function getSearchQuery(array $queryParams, int $size, int $from): array
    {
        $search = $queryParams['query_term'];
        $queryTime = $queryParams['query_datetime'];

        $queryString =
            '{
              "from": %from%,
              "size": %size%,
              "min_score":0.1,
              "query": 
               {
                  "function_score": {
                    "query": {
                      "bool": {
                        "should": [
                          {
                            "match": {
                              "merchantname": {
                                "query": %search_term%,
                                "boost": 15
                              }
                            }
                          },
                          {
                            "match": {
                              "descriptionlong": {
                                "query": %search_term%,
                                "boost": -1
                              }
                            }
                          },
                          {
                            "match": {
                              "keywords": {
                                "query": %search_term%,
                                "boost":3
                              }
                            }
                          }
                        ],
                        "filter": [
                          { "term": { "status": 1 } },
                          {
                            "has_child": {
                              "type":       "merchant_tier",
                              "filter": [
                                { "range": { "startdate" : { "lte" : "%query_time%" }}},
                                { "range": { "enddate" : { "gte" : "%query_time%" }}}
                              ]
                            }
                          }
                        ]                        
                      }
                    }
                  }
                },
              "sort": {
                "_script": {
                  "type": "number",
                  "order": "desc",
                  "script": {
                    "stored" : "merchant-search-sort",
                    "params" : {
                      "search_term" : %search_term%
                    }                  
                  }
                }
              },
              "highlight": {
                "fields": {
                  "merchantname": {
                    "fragment_size": 50,
                    "number_of_fragments": 1
                  },
                  "descriptionlong": {
                    "fragment_size": 50,
                    "number_of_fragments": 1
                  },
                  "keywords": {
                    "fragment_size": 50,
                    "number_of_fragments": 1
                  }
                }
              },
              "aggs": {
                "merchant_searchs": {
                  "terms": {
                    "field": "_uid",
                    "size": 10000
                  },
                  "aggs": {
                    "merchant_tiers": {
                      "children": {
                        "type": "merchant_tier"
                      },
                      "aggs": {
                        "date_filtered": {
                          "filter": {
                            "bool": {
                              "filter": [
                                {
                                  "range": {
                                    "startdate": {
                                      "lte": "%query_time%"
                                    }
                                  }
                                },
                                {
                                  "range": {
                                    "enddate": {
                                      "gte": "%query_time%"
                                    }
                                  }
                                }
                              ]
                            }
                          },
                          "aggs": {
                            "top-cashbackrates": {
                              "top_hits": {
                                "sort": [
                                  {
                                    "cashbackrate": {
                                      "order": "desc"
                                    }
                                  }
                                ],
                                "size": 1
                              }
                            }
                          }
                        }
                      }
                    }
                  }
                }
              }                           
            }';

        //$currentTime = '2017-12-13 23:59:00.000 Australia/Sydney';
        $queryString = str_replace('%search_term%', json_encode($search, JSON_UNESCAPED_SLASHES), $queryString);
        $queryString = str_replace('%from%', $from, $queryString);
        $queryString = str_replace('%size%', $size, $queryString);
        $queryString = str_replace('%query_time%', $queryTime, $queryString);

        return ['body' => $queryString];
    }

    /**
     * Get autocomplete suggestions
     *
     * @param string $query
     * @return array
     */
    public function autoComplete(string $query): array
    {
        $searchQuery = $this->generateAutoCompleteQuery($query);
        $searchResult = $this->getSource($this->searchEs($searchQuery));
        return $searchResult;
    }

    /**
     * Generate auto complete suggestion query
     *
     * @author Naresh Maharjan <naresh.maharjan@cahsrewards.com>
     * @param string $search
     * @return array
     */
    public function generateAutoCompleteQuery(string $search): array
    {
        $searchQuery = [
            'body' => '{
               "_source":["merchantname","mediumimageurlsecure", "smallimageurlsecure", "regularimageurlsecure", "baserate", "descriptionshort"],
               "query": {
                    "bool": {
                      "should": [
                        {
                          "match": {
                            "merchantname": {
                              "query": "%search_term%",
                              "boost": 15
                            }
                          }
                        },
                        {
                          "match": {
                            "descriptionlong": {
                              "query": "%search_term%",
                              "boost": 10
                            }
                          }
                        },
                        {
                          "match": {
                            "keywords": {
                              "query": "%search_term%",
                              "boost": 5
                            }
                          }
                        }
                      ]
                    }
                  },
                  "highlight": {
                    "fields": {
                      "merchantname": {
                        "fragment_size": 50,
                        "number_of_fragments": 1
                      },
                      "descriptionlong": {
                        "fragment_size": 50,
                        "number_of_fragments": 1
                      },
                      "keywords": {
                        "fragment_size": 50,
                        "number_of_fragments": 1
                      }
                    }
                  }
                }'
        ];
        $str = str_replace('%search_term%', $search, $searchQuery);
        return $str;
    }

    /**
     * Get source from search result
     *
     * @author Naresh Maharjan <naresh.maharjan@cashrewards.com>
     * @param $searchResult
     * @return array
     */
    public function getSource($searchResult): array
    {
        $returnArray = [];
        foreach ($this->getHits($searchResult) as $result) {
            $res['score'] = $result['_score'];
            $res['source'] = $result['_source'];
            array_push($returnArray, $res);
        }

        return $returnArray;
    }

    /**
     * get [hits][hits] node from search result
     *
     * @author Naresh Maharjan <naresh.maharjan@cashrewards.com>
     * @param Collection $searchResult
     * @return array
     */
    public function getHits(Collection $searchResult): array
    {
        return $searchResult['hits']['hits'] ?? [];
    }
}