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
class SearchRepository extends ElasticSearchRepository implements EsSearchRepositoryInterface
{
    use ElasticSearchUtils;

    /**
     * Elastic search index
     *
     * @var string
     */
    protected $index = 'logstash-cr-db-merchants-search-public';
    protected $index_type = 'cr-db-merchants-search-public';

    public function __construct()
    {
        if (! empty(env('SEARCH_ES_HOST'))) {
            $this->hosts = [env('SEARCH_ES_HOST')];
        }
    }



    public function searchData(array $params = []): Collection
    {
        return $this->searchEs($params);
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
                      "filter": {
                        "term": {
                          "status": 1
                        }
                      }
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
              }
            }';

        $queryString = str_replace('%search_term%', json_encode($search, JSON_UNESCAPED_SLASHES), $queryString);
        $queryString = str_replace('%from%', $from, $queryString);
        $queryString = str_replace('%size%', $size, $queryString);

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