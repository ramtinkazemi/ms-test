<?php

namespace Tests\Unit\Exceptions;

use Illuminate\Http\Request;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionClass;

class HandlerTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testReport()
    {
        $exception = new \Exception('foo');

        $handler = $this->getMockBuilder(\App\Exceptions\Handler::class)
            ->disableOriginalConstructor()
            ->setMethods(['shouldntReport', 'make', 'error'])
            ->getMock();

        $handler
            ->expects($this->any())
            ->method('shouldntReport')
            ->willReturn(false);

        $reflection = new ReflectionClass($handler);
        $reflection_property = $reflection->getProperty('container');
        $reflection_property->setAccessible(true);
        $reflection_property->setValue($handler, $handler);

        $handler
            ->expects($this->any())
            ->method('make')
            ->willReturnSelf();

        $handler->expects($this->any())
            ->method('error')
            ->will( $this->returnCallback ( function ($e) use (&$res)  {
                $res = $e;
            }) );

        $handler->report($exception);

        $this->assertSame($exception, $res);

    }

    public function testRender()
    {
        $exception = new \Exception('foo');

        $handler = $this->getMockBuilder(\App\Exceptions\Handler::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $request = Request::createFromGlobals();

        $tmp = config('app.default_error_response_type');
        config(['app.default_error_response_type' => 'html']);


        $res = $handler->render($request, $exception);
        $this->assertInstanceOf(\Illuminate\Http\Response::class, $res);
        config(['app.default_error_response_type' => $tmp]);
    }

    public function testRenderWithJson()
    {
        $handler = \Mockery::mock(\App\Exceptions\Handler::class)->makePartial();
        //$handler->shouldReceive('getClient')->andReturnSelf();


        $request =  \Mockery::mock(\Illuminate\Http\Request::class)->makePartial();
        $request->shouldReceive('expectsJson')->andReturn(true);

        $exception = new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('TEST EXCEPTION');
        $res = $handler->render($request, $exception );
        $content = $res->getContent();

        $this->assertJson($content);
        $this->assertEquals(404, $res->getStatusCode());
    }

    public function testUnauthenticated()
    {
        $exception = new \Illuminate\Auth\AuthenticationException('foo');

        $handler = $this->getMockBuilder(\App\Exceptions\Handler::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();


        $request = $this->getMockBuilder(\Illuminate\Http\Request::class)
            ->disableOriginalConstructor()
            ->setMethods(['expectsJson'])
            ->getMock();

        $request
            ->expects($this->any())
            ->method('expectsJson')
            ->willReturn(true);

        $res = $handler->render($request, $exception);
        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $res);

        //----------------------------------

        $request2 = $this->getMockBuilder(\Illuminate\Http\Request::class)
            ->disableOriginalConstructor()
            ->setMethods(['expectsJson'])
            ->getMock();

        $request2
            ->expects($this->any())
            ->method('expectsJson')
            ->willReturn(false);


        try {
            $res = $handler->render($request2, $exception);
            $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $res);
        }
        catch ( \Exception $e)
        {
            $this->assertInstanceOf(\InvalidArgumentException::class, $e);
        }

    }

}
