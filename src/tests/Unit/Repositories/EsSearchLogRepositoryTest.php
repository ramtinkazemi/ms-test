<?php

namespace Tests\Unit\Repositories;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class EsSearchLogRepositoryTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testIndex()
    {
        $repo = new \App\Repositories\EsSearchLogRepository();
        $res = $repo->mapping();
        $this->assertArrayHasKey('searchms-searchlog', $res);
    }
}
