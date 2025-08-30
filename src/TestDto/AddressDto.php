<?php
declare(strict_types=1);

namespace DtoMocker\TestDto;

final class AddressDto
{
    public string $street;
    public int $number;
    public ?string $city = null;

    public function __construct(string $street = 'Rua Exemplo', int $number = 123, ?string $city = null)
    {
        $this->street = $street;
        $this->number = $number;
        $this->city = $city;
    }
}
