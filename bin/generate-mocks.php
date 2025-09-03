#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    echo "This script must be run from CLI.\n";
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

use DtoMocker\Command\GenerateMocksCommand;
use Symfony\Component\Console\Application;

$application = new Application('DTO Mocker', '1.0.0');
$application->add(new GenerateMocksCommand());
$application->setDefaultCommand('dto:generate', true);
$application->run();
