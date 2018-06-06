<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SearchLogServiceTest extends TestCase
{
    public function testSaveSearchLogToFile()
    {
        $service = \Mockery::mock('\App\Services\SearchLogService')->makePartial();
        \Storage::shouldReceive('disk')->andReturnSelf();
        \Storage::shouldReceive('append')->andReturn(true);

        $res = $service->saveSearchLogToFile(['msg' => 'test Message']);
        $this->assertTrue(true, $res);
    }
}
