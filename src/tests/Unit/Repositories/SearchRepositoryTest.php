<?php

namespace Tests\Unit\Repositories;

use App\Repositories\SearchRepository;
use Mockery;
use Tests\TestCase;

class SearchRepositoryTest extends TestCase
{

    public function testAutoComplete()
    {
        $searchParams = [
            'body' => '{
               "_source":["merchantname","mediumimageurlsecure", "smallimageurlsecure", "regularimageurlsecure", "baserate", "descriptionshort"],
               "query": {
                    "bool": {
                      "should": [
                        {
                          "match": {
                            "merchantname": {
                              "query": "wwe",
                              "boost": 15
                            }
                          }
                        },
                        {
                          "match": {
                            "descriptionlong": {
                              "query": "wwe",
                              "boost": 10
                            }
                          }
                        },
                        {
                          "match": {
                            "keywords": {
                              "query": "wwe",
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
        $data = collect([
            'hits' => [
                'hits' => [
                    [
                        '_source' => 'this is test data',
                        "_score" => 123.33
                    ]
                ]
            ]
        ]);
        $faqRepo = Mockery::mock(SearchRepository::class)->makePartial();
        $faqRepo->shouldReceive('searchEs')->with($searchParams)->andReturn($data);

        $actual = $faqRepo->autoComplete($searchParams);
        static::assertEquals($data['hits']['hits'][0]['_source'], $actual['hits']['hits'][0]['_source']);
        static::assertEquals($data['hits']['hits'][0]['_score'], $actual['hits']['hits'][0]['_score']);
    }
}
