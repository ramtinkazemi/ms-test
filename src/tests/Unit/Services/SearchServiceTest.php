<?php

namespace Tests\Unit\Services;

use App\Services\SearchService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SearchServiceTest extends TestCase
{
    public function setUp()
    {
        //putenv('APP_LOG_LEVEL=emergency');

        parent::setUp();

        //$nullLogger = new \Monolog\Handler\NullHandler();
        //\Log::getMonolog()->setHandlers(array($nullLogger));
    }

    public function tearDown()
    {
        \Mockery::close();
    }

//    public function testFind()
//    {
//        $data = file_get_contents(__DIR__ . '/find_result_sample.json');
//
//        $service = \Mockery::mock('\App\Services\SearchService')->makePartial();
//        $service->shouldReceive('getClient')->andReturnSelf();
//        $service->shouldReceive('request')->andReturnSelf();
//        $service->shouldReceive('getBody')->andReturnSelf();
//        $service->shouldReceive('getContents')->andReturn($data);
//
//        $res = $service->find([
//            'query' => 'test',
//            'keywords' => 'test',
//            'domain' => '',
//            'domainlanguage' => '',
//            'per_page' => 3,
//            'page' => 1,
//            'fields' => [
//                'id',
//                'client_id',
//                'merchantid',
//                'merchantname',
//                'websiteurl',
//                'status',
//                'regularimageurlsecure',
//                'smallimageurlsecure',
//                'mediumimageurlsecure',
//                'descriptionshort',
//                'descriptionlong',
//                'basicterms',
//                'extentedterms',
//                'cashbackrate',
//                'membercommissionrate',
//                'clientcommissionrate',
//                'commissiontype',
//                'downloadoffer',
//                'offercount',
//                'keywords',
//                'trackinglink',
//                'weight',
//                'clickouts',
//            ]
//        ]);
//
//        $this->assertSame(json_decode($data, true), $res);
//    }

    /**
     * @cover Merchant::makeQuery()
     */
    public function testMakeQuery()
    {
        $service = new \App\Services\SearchService();
        $this->assertSame("(merchantname:(*test*) OR keywords:\"test\" OR descriptionshort:\"test\" OR descriptionlong:\"test\")", $service->makeQuery("test", "", ""));
        $this->assertSame("(merchantname:(*test1* OR *test2*) OR keywords:\"test1 test2\" OR descriptionshort:\"test1 test2\" OR descriptionlong:\"test1 test2\")", $service->makeQuery("test1 test2", "", ""));
        $this->assertSame("(merchantname:(*test1* OR *test2*) OR keywords:\"test1 test2\" OR descriptionshort:\"test1 test2\" OR descriptionlong:\"test1 test2\") AND domain:domain1",
            $service->makeQuery("test1 test2", "domain1", ""));
        $this->assertSame("(merchantname:(*test1* OR *test2*) OR keywords:\"test1 test2\" OR descriptionshort:\"test1 test2\" OR descriptionlong:\"test1 test2\") AND domain:domain1 AND domainlanguage:lang1",
            $service->makeQuery("test1 test2", "domain1", "lang1"));

    }

    public function testGenerateAutoCompleteQuery()
    {
        $query = 'wwe';

        $searchService = new SearchService();
        $expected = [
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
        $actual = $searchService->generateAutoCompleteQuery($query);
        $this->assertEquals($expected, $actual);
    }

    public function testGetHits()
    {

        $data = collect([
            'hits' => [
                'hits' => [
                    [
                        '_source' => 'this is test data'
                    ]
                ]
            ]
        ]);
        $searchService = new SearchService();
        $actual = $searchService->getHits($data);
        $this->assertEquals([['_source' => 'this is test data']], $actual);

    }

    public function testGetSource()
    {
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

        $searchService = new SearchService();
        $actual = $searchService->getSource($data);
        $this->assertEquals([
            [
                'source' => 'this is test data',
                "score" => 123.33
            ]
        ], $actual);
    }

    public function testAutoComplete()
    {
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
        $mock = \Mockery::mock('\App\Repositories\SearchRepository')->makePartial();
        $mock->shouldReceive('autoComplete')->andReturn($data);

        $searchService = new SearchService();
        $actual = $searchService->autoComplete($data, $mock);
        $this->assertEquals([
            [
                'source' => 'this is test data',
                "score" => 123.33
            ]
        ], $actual);
    }

    public function testSetError()
    {
        $service = \Mockery::mock('\App\Services\SearchService')->makePartial();
        $service->setError('TEST ERROR');

        $res = $service->getErrors();
        $this->assertEquals(['TEST ERROR'], $res);
    }

    public function testSearchRepoWithCache()
    {
        $searchOptions = [
            "query" => "dan",
            "domain" => null,
            "domainlanguage" => null,
            "per_page" => 10,
            "page" => 1,
            "fields" => null
        ];
        Cache::shouldReceive('get')->with("search:" . json_encode($searchOptions))->andReturn($this->getCachedSearchResult());

        $searchRepositoryMock = \Mockery::mock('\App\Repositories\SearchRepository');

        $searchService = new SearchService();

        $actual = $searchService->searchRepo($searchRepositoryMock, $searchOptions, ["sort" => []]);

        $this->assertEquals("dan", $actual['summary']['q']);
        $this->assertEquals(10, $actual['summary']['count']);
        $this->assertEquals(10, $actual['summary']['per_page']);
        $this->assertEquals(1, $actual['summary']['page']);
        $this->assertEquals(":", $actual['summary']['sort']);
        $this->assertArrayHasKey('items', $actual);
        $this->assertEquals(10, count($actual['items']));
        $this->assertArrayHasKey('merchants_ids', $actual);
        $this->assertEquals(10, count($actual['merchants_ids']));
        $this->assertEquals(0, $actual['profile']['es_elasped_msec']);
    }

    public function testSearchRepoWithCacheWithSelectedFields()
    {
        $searchOptions = [
            "query" => "dan",
            "domain" => null,
            "domainlanguage" => null,
            "per_page" => 10,
            "page" => 1,
            "fields" => ['status', 'merchantname', 'keywords']
        ];
        Cache::shouldReceive('get')->with("search:" . json_encode($searchOptions))->andReturn($this->getCachedSearchResult(['status', 'merchantname', 'keywords']));

        $searchRepositoryMock = \Mockery::mock('\App\Repositories\SearchRepository');

        $searchService = new SearchService();

        $actual = $searchService->searchRepo($searchRepositoryMock, $searchOptions, ["sort" => []]);

        $this->assertEquals("dan", $actual['summary']['q']);
        $this->assertEquals(10, $actual['summary']['count']);
        $this->assertEquals(10, $actual['summary']['per_page']);
        $this->assertEquals(1, $actual['summary']['page']);
        $this->assertEquals(":", $actual['summary']['sort']);
        $this->assertArrayHasKey('items', $actual);
        $this->assertEquals(10, count($actual['items']));
        $this->assertEquals(3, count($actual['items'][0]));
        $this->assertArrayNotHasKey('websiteurl', $actual['items'][0]);
        $this->assertArrayHasKey('merchants_ids', $actual);
        $this->assertEquals(10, count($actual['merchants_ids']));
        $this->assertEquals(0, $actual['profile']['es_elasped_msec']);
    }

    public function testSearchRepoWithoutCacheAndValidResponse()
    {
        $searchOptions = [
            "query" => "“dan murphy’s“",
            "domain" => null,
            "domainlanguage" => null,
            "per_page" => 10,
            "page" => 1,
            "fields" => null
        ];
        Cache::shouldReceive('get')->once()->with("search:" . json_encode($searchOptions));
        Cache::shouldReceive('put')->once();

        $searchRepositoryMock = \Mockery::mock('\App\Repositories\SearchRepository')->makePartial();
        $searchRepositoryMock->shouldReceive('searchData')->once()->andReturn(collect($this->getESSearchResult()));

        $searchService = new SearchService();

        $actual = $searchService->searchRepo($searchRepositoryMock, $searchOptions, ["sort" => []]);

        $this->assertEquals("“dan murphy’s“", $actual['summary']['q']);
        $this->assertEquals(10, $actual['summary']['count']);
        $this->assertEquals(10, $actual['summary']['per_page']);
        $this->assertEquals(1, $actual['summary']['page']);
        $this->assertEquals(":", $actual['summary']['sort']);
        $this->assertArrayHasKey('items', $actual);
        $this->assertEquals(10, count($actual['items']));
        $this->assertEquals(25, count($actual['items'][0]));
        $this->assertArrayHasKey('websiteurl', $actual['items'][0]);
        $this->assertArrayHasKey('merchants_ids', $actual);
        $this->assertEquals(10, count($actual['merchants_ids']));
        $this->assertEquals(83, $actual['profile']['es_elasped_msec']);
    }
    public function testSearchRepoWithoutCacheAndValidResponseAndUnicodeSearchInput()
    {
        $searchOptions = [
            "query" => "dan",
            "domain" => null,
            "domainlanguage" => null,
            "per_page" => 10,
            "page" => 1,
            "fields" => null
        ];
        Cache::shouldReceive('get')->once()->with("search:" . json_encode($searchOptions));
        Cache::shouldReceive('put')->once();

        $searchRepositoryMock = \Mockery::mock('\App\Repositories\SearchRepository')->makePartial();
        $searchRepositoryMock->shouldReceive('searchData')->once()->andReturn(collect($this->getESSearchResult()));

        $searchService = new SearchService();

        $actual = $searchService->searchRepo($searchRepositoryMock, $searchOptions, ["sort" => []]);

        $this->assertEquals("dan", $actual['summary']['q']);
        $this->assertEquals(10, $actual['summary']['count']);
        $this->assertEquals(10, $actual['summary']['per_page']);
        $this->assertEquals(1, $actual['summary']['page']);
        $this->assertEquals(":", $actual['summary']['sort']);
        $this->assertArrayHasKey('items', $actual);
        $this->assertEquals(10, count($actual['items']));
        $this->assertEquals(25, count($actual['items'][0]));
        $this->assertArrayHasKey('websiteurl', $actual['items'][0]);
        $this->assertArrayHasKey('merchants_ids', $actual);
        $this->assertEquals(10, count($actual['merchants_ids']));
        $this->assertEquals(83, $actual['profile']['es_elasped_msec']);
    }



    public function testSearchRepoWithoutCacheAndEmptyResponse()
    {
        $searchOptions = [
            "query" => "dan",
            "domain" => null,
            "domainlanguage" => null,
            "per_page" => 10,
            "page" => 1,
            "fields" => null
        ];
        Cache::shouldReceive('get')->once()->with("search:" . json_encode($searchOptions));

        $searchRepositoryMock = \Mockery::mock('\App\Repositories\SearchRepository')->makePartial();
        $searchRepositoryMock->shouldReceive('searchData')->once()->andReturn(collect([]));

        $searchService = new SearchService();

        $actual = $searchService->searchRepo($searchRepositoryMock, $searchOptions, ["sort" => []]);
        $this->assertNull($actual);
    }

    public function testSearchRepoWithoutCacheValidResponseAndCustomFields()
    {
        $searchOptions = [
            "query" => "dan",
            "domain" => null,
            "domainlanguage" => null,
            "per_page" => 10,
            "page" => 1,
            "fields" => ['status', 'merchantname', 'keywords']
        ];
        Cache::shouldReceive('get')->once()->with("search:" . json_encode($searchOptions));
        Cache::shouldReceive('put')->once();

        $searchRepositoryMock = \Mockery::mock('\App\Repositories\SearchRepository')->makePartial();
        $searchRepositoryMock->shouldReceive('searchData')->once()->andReturn(collect($this->getESSearchResult()));

        $searchService = new SearchService();

        $actual = $searchService->searchRepo($searchRepositoryMock, $searchOptions, ["sort" => []]);

        $this->assertEquals("dan", $actual['summary']['q']);
        $this->assertEquals(10, $actual['summary']['count']);
        $this->assertEquals(10, $actual['summary']['per_page']);
        $this->assertEquals(1, $actual['summary']['page']);
        $this->assertEquals(":", $actual['summary']['sort']);
        $this->assertArrayHasKey('items', $actual);
        $this->assertEquals(10, count($actual['items']));
        $this->assertEquals(3, count($actual['items'][0]));
        $this->assertArrayNotHasKey('websiteurl', $actual['items'][0]);
        $this->assertArrayHasKey('merchants_ids', $actual);
        $this->assertEquals(10, count($actual['merchants_ids']));
        $this->assertEquals(83, $actual['profile']['es_elasped_msec']);
    }


    private function getCachedSearchResult($fields = [])
    {

        $cachedData = file_get_contents(__DIR__ . '/cachedSearchResult.json');
        if ($fields) {
            $arr = json_decode($cachedData);
            $items = collect($arr->items)->map(function ($value) use ($fields) {
                return collect($value)->only($fields);
            });
            $arr->items = $items;
            $cachedData = json_encode($arr);
        }
        return $cachedData;
    }

    private function getESSearchResult()
    {
        $searchResult = file_get_contents(__DIR__ . '/searchResult.json');

        return json_decode($searchResult, true);
    }
}
