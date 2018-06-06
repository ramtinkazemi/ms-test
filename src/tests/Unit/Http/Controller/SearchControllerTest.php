<?php

namespace Tests\Unit\Http\Controller;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;

class SearchControllerTest extends TestCase
{
    protected $base_api_uri = 'api/v1';

    public function setUp()
    {
        putenv('APP_LOG_LEVEL=error');

        parent::setUp();

        //$nullLogger = new \Monolog\Handler\NullHandler();
        //\Log::getMonolog()->setHandlers(array($nullLogger));
    }

    public function tearDown()
    {
        \Mockery::close();
    }

    public function testIndex()
    {
        $data = file_get_contents(__DIR__ . '/search_result_sample.json');

        $service = \Mockery::mock(\App\Services\SearchService::class);
        $service->shouldReceive('searchRepo')->once()->andReturn(json_decode($data, true));

        $this->app->instance(\App\Services\SearchService::class, $service);

        $logService = \Mockery::mock(\App\Services\SearchLogService::class);
        $logService->shouldReceive('saveSearchLogAsync')->once()->andReturn(true);
        $this->app->instance(\App\Services\SearchLogService::class, $logService);

        $tmp_conf = config('app.env');
        config(['app.env' => 'local']);

        $fields = 'id,client_id,merchantid,merchantname,websiteurl,status';
        $res = $this->get($this->base_api_uri.'/search?q=test&per_page=2&fields='.$fields, ['Cache-Control' => 'no-cache']);
        $data = json_decode($res->getContent(), true);
        $res->assertStatus(200);
        //$this->assertArrayHasKey('summary', $data);

        config(['app.env' => $tmp_conf]);
    }

    public function testIndexWithNullSearchReturn()
    {
        $service = \Mockery::mock(\App\Services\SearchService::class);
        $service->shouldReceive('searchRepo')->once()->andReturn(null);
        $service->shouldReceive('getErrors')->andReturn([
            new \App\Services\ServiceException(
                'ERROR TEST',
                ['ref_code' => 1000, 'key1' => 'value1']
            )
        ]);

        $this->app->instance(\App\Services\SearchService::class, $service);

        $logService = \Mockery::mock(\App\Services\SearchLogService::class);
        $logService->shouldReceive('saveSearchLogAsync')->andReturn(true);

        $this->app->instance(\App\Services\SearchLogService::class, $logService);

        $fields = 'id,client_id,merchantid,merchantname,websiteurl,status';
        $res = $this->get($this->base_api_uri.'/search?q=test&per_page=0&fields='.$fields, ['Cache-Control' => 'no-cache']);
        $data = json_decode($res->getContent(), true);
        $res->assertStatus(500);
        //$this->assertArrayHasKey('summary', $data);
    }

    public function testIndexWithEmptySearchReturn()
    {
        $service = \Mockery::mock(\App\Services\SearchService::class);
        $service->shouldReceive('searchRepo')->once()->andReturn([]);
        $this->app->instance(\App\Services\SearchService::class, $service);

        $logService = \Mockery::mock(\App\Services\SearchLogService::class);
        $logService->shouldReceive('saveSearchLogAsync')->andReturn(true);
        $this->app->instance(\App\Services\SearchLogService::class, $logService);

        $fields = 'id,client_id,merchantid,merchantname,websiteurl,status';
        $res = $this->get($this->base_api_uri.'/search?q=test&per_page=0&fields='.$fields, ['Cache-Control' => 'no-cache']);
        $data = json_decode($res->getContent(), true);
        $res->assertStatus(400);
        //$this->assertArrayHasKey('summary', $data);
    }

    public function testIndexWithShortQ()
    {
        $fields = 'id,client_id,merchantid,merchantname,websiteurl,status';
        $res = $this->get($this->base_api_uri.'/search?q=te&fields='.$fields, ['Cache-Control' => 'no-cache']);
        $data = json_decode($res->getContent(), true);
        $res->assertStatus(200);
    }


    public function testIndexWithSort()
    {
        $fields = 'id,client_id,merchantid,merchantname,websiteurl,status';
        $res = $this->get($this->base_api_uri.'/search?q=test&sort=merchantname,relevance:asc', ['Cache-Control' => 'no-cache']);
        $data = json_decode($res->getContent(), true);
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('sort', $data['summary']);


    }

    public function testParseSortArgument()
    {
        $controller = \Mockery::mock(\App\Http\Controllers\SearchController::class)->makePartial();

        $sort_str = null;
        $res = $controller->parseSortArgument($sort_str);
        $this->assertSame([], $res);

        $sort_str = '';
        $res = $controller->parseSortArgument($sort_str);
        $this->assertSame([], $res);

        $sort_str = 'merchantname,relevance:asc';
        $res = $controller->parseSortArgument($sort_str);
        $this->assertSame(['merchantname' => null, 'relevance' => 'asc'], $res);


        $sort_str = 'merchantname:';
        $res = $controller->parseSortArgument($sort_str);
        $this->assertSame(['merchantname' => '' ], $res);

    }
/*
    public function testIndexWithRedis()
    {
        $fields = 'id,client_id,merchantid,merchantname,websiteurl,status';
        $res = $this->get('api/search?q=test&fields='.$fields);
        $data = json_decode($res->getContent(), true);
        $this->assertArrayHasKey('summary', $data);
    }


*/
}
