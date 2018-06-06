<?php

namespace Tests\Unit\Http\Middleware;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class RedirectIfAuthenticatedTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testHandle()
    {
        $request = $this->getMockBuilder(\Illuminate\Http\Request::class)
            ->disableOriginalConstructor()
            ->setMethods()
            ->getMock();

        $middleware = $this->getMockBuilder(\App\Http\Middleware\RedirectIfAuthenticated::class)
            ->disableOriginalConstructor()
            ->setMethods(['check'])
            ->getMock();

        $res = $middleware->handle($request, function () { return 'foo';});
        $this->assertEquals('foo', $res);

        //-------------------------
        $guard = 'api';
        $middleware->expects( $this->any() )
            ->method('check')
            ->willReturn( true );

        // Redirect Chain call to target object($middleware) for easy override method
        \Auth::shouldReceive('guard')
            ->once()
            ->andReturn($middleware);

        $res = $middleware->handle($request, function() {}, $guard);
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $res);
    }
}
