<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use DtoMocker\Command\GenerateMocksCommand;

final class CommandTest extends TestCase
{
    public function test_command_runs(): void
    {
        $app = new Application();
        $app->add(new GenerateMocksCommand());

        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'dto:generate', 'path' => 'src/TestDto', '--out' => 'tests/_fixtures', '--count' => 1]);

        $this->assertSame(0, $tester->getStatusCode());
    }
}
