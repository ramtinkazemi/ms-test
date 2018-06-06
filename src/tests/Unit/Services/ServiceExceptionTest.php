<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ServiceExceptionTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testGetMessageEx()
    {
        $message = "MESSAGE";
        $context = ['id' => 1, 'timestamp' => microtime(true)];
        $a = new \App\Services\ServiceException($message, $context, 500);
        $expect = "MESSAGE".' '.json_encode($context);
        $msg = $a->getMessageEx();
        $this->assertEquals($expect, $msg);

        $message = "";
        $context = ['id' => 1, 'timestamp' => microtime(true)];
        $a = new \App\Services\ServiceException($message, $context, 500);
        $expect = json_encode($context);
        $msg = $a->getMessageEx();
        $this->assertEquals($expect, $msg);
    }

    public function testGetContext()
    {
        $message = "MESSAGE";
        $context = ['id' => 1, 'timestamp' => microtime(true)];
        $a = new \App\Services\ServiceException($message, $context, 500);
        $this->assertSame($context, $a->getContext());
    }
}
