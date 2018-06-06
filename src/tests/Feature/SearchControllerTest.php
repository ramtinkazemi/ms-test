<?php

namespace Tests\Feature;

use App\Jobs\LogSearchClick;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

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

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testExample()
    {
        $res = $this->get($this->base_api_uri . '/search?q=test');
        $data = json_decode($res->getContent(), true);
        $this->assertArrayHasKey('summary', $data);
    }

    public function testWithoutQ()
    {
        $res = $this->get($this->base_api_uri . '/search?');
        $data = json_decode($res->getContent(), true);
        $this->assertJson($res->getContent());
    }

    public function testPerPage()
    {
        \Config::set('app.essize', 0);
        $res = $this->get($this->base_api_uri . '/search?q=test');
        $data = json_decode($res->getContent(), true);
        $this->assertJson($res->getContent());
    }

    public function testIndex()
    {
        $fields = 'id,client_id,merchantid,merchantname,websiteurl,status';
        $res = $this->get($this->base_api_uri . '/search?q=test&per_page=0&fields=' . $fields, ['Cache-Control' => 'no-cache']);
        $data = json_decode($res->getContent(), true);
        $this->assertArrayHasKey('summary', $data);
    }

    public function testIndexWithSort()
    {
        $fields = 'id,client_id,merchantid,merchantname,websiteurl,status';
        $res = $this->get($this->base_api_uri . '/search?q=test&sort=merchantname', ['Cache-Control' => 'no-cache']);
        $data = json_decode($res->getContent(), true);
        $this->assertArrayHasKey('summary', $data);
    }

    public function testIndexWithRedis()
    {
        $fields = 'id,client_id,merchantid,merchantname,websiteurl,status';
        $res = $this->get($this->base_api_uri . '/search?q=test&fields=' . $fields);
        $data = json_decode($res->getContent(), true);
        $this->assertArrayHasKey('summary', $data);
    }

    public function testIndexWithShortQ()
    {
        $fields = 'id,client_id,merchantid,merchantname,websiteurl,status';
        $res = $this->get($this->base_api_uri . '/search?q=te&fields=' . $fields, ['Cache-Control' => 'no-cache']);
        $data = json_decode($res->getContent(), true);
        $res->assertStatus(200);
    }

    public function testSuccessfulSearchClickLog()
    {
        Queue::fake();

        $searchLog["timestamp"] = "2017-09-22 00:49:58.759007";
        $searchLog["merchantId"] = rand(1, 100);
        $searchLog["merchantName"] = "Test Merchant";
        $searchLog["memberId"] = rand(100, 200);
        $searchLog["clientId"] = rand(200, 300);
        $searchLog["searchTerm"] = "test search term";
        $searchLog["searchWeight"] = "65.322";


        $response = $this->post("api/v1/click/log", $searchLog);
        Queue::assertPushed(LogSearchClick::class);
        $this->assertEquals(202, $response->getStatusCode());
        $this->assertEquals('{"status":"success","messages":{"Accepted":"Processing request"}}', $response->getContent());
    }

    public function testUnsuccessfulSearchClickLog()
    {
        $response = $this->post('api/v1/click/log',[]);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('{"status":"error","messages":"{\"merchantId\":[\"The merchant id field is required.\"],\"merchantName\":[\"The merchant name field is required.\"],\"searchTerm\":[\"The search term field is required.\"],\"searchWeight\":[\"The search weight field is required.\"]}"}', $response->getContent());
    }

    public function testAutoCompleteWithSearchQuery()
    {
        $url = $this->base_api_uri.'/autocomplete?q=wwe';
        $result = $this->get($url);
        $data = json_decode($result->getContent());

        $this->assertEquals('success', $data->status);
        $this->assertEquals('WWE Shop', $data->messages[0]->source->merchantname);
    }


    public function testAutoCompleteWithoutSearchQuery()
    {
        $url = $this->base_api_uri.'/autocomplete?q=';
        $result = $this->get($url);
        $data = json_decode($result->getContent());

        $this->assertEquals('success', $data->status);
        $this->assertEquals([], $data->messages);
    }

    public function testNewSearchQuery()
    {
        $service = resolve(\App\Services\SearchService::class);
        if (get_class($service->getSearchRepository()) == \App\Repositories\SearchRepositoryV2::class) {
            $service->createSearchSortingScript();
            $repo = $service->getSearchRepository();

            $docs['1_1'] = json_decode(file_get_contents(__DIR__ . '/data/sample_doc1-1.json'), TRUE);
            $docs['1_1_1'] = json_decode(file_get_contents(__DIR__ . '/data/sample_doc1-1-tier-1.json'), TRUE);
            $docs['1_1_2'] = json_decode(file_get_contents(__DIR__ . '/data/sample_doc1-1-tier-2.json'), TRUE);
            $docs['2_1'] = json_decode(file_get_contents(__DIR__ . '/data/sample_doc2-1.json'), TRUE);
            $docs['2_1_1'] = json_decode(file_get_contents(__DIR__ . '/data/sample_doc2-1-tier-1.json'), TRUE);
            $docs['2_1_2'] = json_decode(file_get_contents(__DIR__ . '/data/sample_doc2-1-tier-2.json'), TRUE);
            $docs['2_2'] = json_decode(file_get_contents(__DIR__ . '/data/sample_doc2-2.json'), TRUE);
            $docs['2_2_1'] = json_decode(file_get_contents(__DIR__ . '/data/sample_doc2-2-tier-1.json'), TRUE);
            $docs['2_2_2'] = json_decode(file_get_contents(__DIR__ . '/data/sample_doc2-2-tier-2.json'), TRUE);

            //$repo->deleteIndex();
            $template = file_get_contents(__DIR__ . '/data/logstash-cr-db-merchants-search-public-v2.json');
            $repo->putIndexTemplate('logstash-cr-db-merchants-search-public-v2', $template);


            $repo->index($docs['1_1'], ['type' => 'merchant_search']);
            $repo->index($docs['1_1_1'], ['type' => 'merchant_tier', 'parent' => '1000103-']);
            $repo->index($docs['1_1_2'], ['type' => 'merchant_tier', 'parent' => '1000103-']);
            $repo->index($docs['2_1'], ['type' => 'merchant_search']);
            $repo->index($docs['2_1_1'], ['type' => 'merchant_tier', 'parent' => '1000104-']);
            $repo->index($docs['2_1_2'], ['type' => 'merchant_tier', 'parent' => '1000104-']);
            $repo->index($docs['2_2'], ['type' => 'merchant_search']);
            $repo->index($docs['2_2_1'], ['type' => 'merchant_tier', 'parent' => '1000104-2000001']);
            $repo->index($docs['2_2_2'], ['type' => 'merchant_tier', 'parent' => '1000104-2000001', 'refresh' => 'wait_for']);



            $res = $this->get($this->base_api_uri . '/search?q=Fragrance Net&datetime=2017-12-14 23:59:59.999 Australia/Sydney');
            $data = json_decode($res->getContent(), true);

            $this->assertSame('1000104-', $data['items'][0]['id']);
            $this->assertSame(".420000", $data['items'][0]['cashbackrate']);
            $this->assertSame('1000103-', $data['items'][1]['id']);
            $this->assertSame(".410000", $data['items'][1]['cashbackrate']);
            $this->assertSame('1000104-2000001', $data['items'][2]['id']);
            $this->assertSame(".450000", $data['items'][2]['cashbackrate']);
        }

    }
}
