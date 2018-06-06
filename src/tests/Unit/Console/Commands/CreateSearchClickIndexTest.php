<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\CreateSearchClickIndex;
use App\Repositories\SearchClickRepository;
use Mockery;
use Tests\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CreateSearchClickIndexTest extends TestCase
{


    public function testValidCreateSearchClickIndex()
    {
        $searchClickRepo = Mockery::mock(SearchClickRepository::class);
        $searchClickRepo->shouldReceive('setIndices')->once()->andReturnSelf();
        $searchClickRepo->shouldReceive('createIndex')->once()->andReturn(true);

        $app = new Application();
        $command = new CreateSearchClickIndex($searchClickRepo);
        $command->setLaravel(app());
        $app->add($command);

        $cmd = $app->find($command->getName());

        $tester = new CommandTester($cmd);
        $tester->execute([
            'command' => $cmd->getName()
        ]);
        $this->assertEquals("Index created successfully\n", $tester->getDisplay());
    }


    public function testUnsuccessfulCreateFaqIndex()
    {
        $searchClickRepo = Mockery::mock(SearchClickRepository::class);
        $searchClickRepo->shouldReceive('setIndices')->once()->andReturnSelf();
        $searchClickRepo->shouldReceive('createIndex')->once()->andReturn(false);

        $app = new Application();
        $command = new CreateSearchClickIndex($searchClickRepo);
        $command->setLaravel(app());
        $app->add($command);

        $cmd = $app->find($command->getName());

        $tester = new CommandTester($cmd);
        $tester->execute([
            'command' => $cmd->getName()
        ]);

        $this->assertEquals("Could not process task\n", $tester->getDisplay());
    }
}
