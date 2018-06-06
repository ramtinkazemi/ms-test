<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ConsoleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testExample()
    {
        $kernel = $this->app->make(\App\Console\Kernel::class);
        $status = $kernel->handle(
            $input = new \Symfony\Component\Console\Input\ArrayInput([
                'command' => 'env',
            ]),
            $output = new \Symfony\Component\Console\Output\BufferedOutput
        );
        $console_output = $output->fetch();

        $this->assertStringStartsWith("Current application environment", $console_output);
    }
}
