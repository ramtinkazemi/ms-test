<?php

namespace Tests\Unit\Providers;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class BroadcastServiceProviderTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testExample()
    {
        $provider = $this->getMockBuilder(\App\Providers\BroadcastServiceProvider::class)
            ->setMethods()
            ->disableOriginalConstructor()
            ->getMock();

        $provider->boot();
    }
}
