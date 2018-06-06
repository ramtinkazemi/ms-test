<?php

namespace Tests\Unit\Exceptions;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ExceptionHandlerJsonTest extends TestCase
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

    public function testGetJson()
    {
        $handler = \Mockery::mock(\App\Exceptions\ExceptionHandlerJson::class,array(true, null, '%f-%l'))->makePartial();
        //$handler->shouldReceive('getClient')->andReturnSelf();

        $exception = new \Symfony\Component\HttpKernel\Exception\HttpException('TEST EXCEPTION', 403);
        $res = $handler->getJson($exception);

        $this->assertJson($res);
        $this->assertArrayHasKey('errors', json_decode($res, true));
    }

    public function testGetContent()
    {
        $handler = \Mockery::mock(\App\Exceptions\ExceptionHandlerJson::class, array(true))->makePartial();
        //$e = \Symfony\Component\Debug\Exception\FlattenException::create($e);

        $e = new \Symfony\Component\HttpKernel\Exception\HttpException(403, 'TEST_EXCEPTION');
        $e = \Symfony\Component\Debug\Exception\FlattenException::create($e);
        $json = json_encode($e->getTrace());

        //$trace = ['class' => 'test_class', 'file' => 'test_file', 'line' => '1000', 'arg' => ]

        $trace = [
                    [
                        "namespace" => "",
                        "short_class" => "PHPUnit_TextUI_Command",
                        "class" => "PHPUnit_TextUI_Command",
                        "type" => "->",
                        "function" => "run",
                        "file" => "\/var\/www\/html\/vendor\/phpunit\/phpunit\/src\/TextUI\/Command.php",
                        "line" => 116,
                        "args" => [
                                "string" => "vendor\/bin\/phpunit",
                                "boolean" => true,
                                "resource" => tmpfile(),
                        ]
                    ]
                ];

        $e->setTrace($trace, 'test_file', '1000');
        $res = $handler->getContent($e);
        $this->assertArrayHasKey('errors', $res);
        $this->assertEquals(403, $res['errors'][0]['status']);
    }


    public function testGetContentWithException()
    {
        $handler = \Mockery::mock(\App\Exceptions\ExceptionHandlerJson::class, array(true))->makePartial();
        //$e = \Symfony\Component\Debug\Exception\FlattenException::create($e);

        $e = \Mockery::mock(\Symfony\Component\Debug\Exception\FlattenException::class)->makePartial();
        $e->shouldReceive('getAllPrevious')->andThrow(\Exception::class, 'TEST EXCEPTION');
        $res = $handler->getContent($e);

        $this->assertArrayHasKey('errors', $res);
        $this->assertStringStartsWith('Exception thrown when', array_pop($res['errors'])['title']);
    }
}
