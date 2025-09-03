<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use DtoMocker\Util\ClassScanner;

final class ScannerTest extends TestCase
{
    public function test_scan_find_sample_dtos(): void
    {
        $scanner = new ClassScanner();
        $map = $scanner->scan(__DIR__ . '/../src/TestDto');

        $this->assertArrayHasKey('DtoMocker\\TestDto\\AddressDto', $map);
        $this->assertArrayHasKey('DtoMocker\\TestDto\\BaseDto', $map);
        $this->assertArrayHasKey('DtoMocker\\TestDto\\ConstructedDto', $map);
    }
}
