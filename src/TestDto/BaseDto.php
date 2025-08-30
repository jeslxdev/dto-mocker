<?php
declare(strict_types=1);

namespace DtoMocker\TestDto;

final class BaseDto
{
    public string $name;
    public int $age;
    public bool $active;
    public ?AddressDto $address = null;

    public function __construct()
    {
        // defaults will be overwritten by DtoMocker when generating
        $this->name = '';
        $this->age = 0;
        $this->active = false;
    }
}
