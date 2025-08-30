<?php
declare(strict_types=1);

namespace DtoMocker\TestDto;

final class ConstructedDto
{
    public string $id;
    public int $value;

    public function __construct(string $id, int $value = 42)
    {
        $this->id = $id;
        $this->value = $value;
    }
}
