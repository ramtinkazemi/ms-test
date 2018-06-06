<?php

namespace Tests\Unit\Jobs;

use App\Facades\SearchClickLog;
use App\Jobs\LogSearchClick;
use Mockery;
use Tests\TestCase;

class LogSearchClickTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testExample()
    {
        $this->assertTrue(true);
    }


    /**
     * A basic test example.
     *
     * @return void
     */
    public function testHandle()
    {
        $mock = Mockery::mock('App\Repositories\SearchClickRepository')->makePartial();

        $searchLog["timestamp"] = "2017-09-22 00:49:58.759007";
        $searchLog["merchantId"] = rand(1,100);
        $searchLog["merchantName"] = "Test Merchant";
        $searchLog["memberId"] = rand(100,200);
        $searchLog["clientId"] = rand(200,300);
        $searchLog["searchTerm"] = "test search term";
        $searchLog["searchWeight"] = "65.322";


        SearchClickLog::shouldReceive('log')->once()->andReturnNull();
        $job = new LogSearchClick($mock, $searchLog);
        $actual = $job->handle();

        $this->assertNull($actual);
    }
}
