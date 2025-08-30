<?php

use PHPUnit\Framework\TestCase;
use DtoMocker\DtoMocker;

class AddressDto
{
    public string $street;
    public int $number;
}

class UserDto
{
    public string $name;
    public int $age;
    public bool $active;
    public ?AddressDto $address = null;
}

class ConstructedDto
{
    public function __construct(public string $id, public int $value = 42)
    {
    }
}

final class DtoMockerTest extends TestCase
{
    public function test_it_creates_dto_mock(): void
    {
        $mocker = new DtoMocker();
        $user = $mocker->make(UserDto::class);

        $this->assertInstanceOf(UserDto::class, $user);
        $this->assertIsString($user->name);
        $this->assertIsInt($user->age);
        $this->assertIsBool($user->active);
    }

    public function test_nested_dto_is_generated(): void
    {
        $mocker = new DtoMocker();
        $user = $mocker->make(UserDto::class);

        // address may be null sometimes because it's nullable, but if set it must be AddressDto
        if ($user->address !== null) {
            $this->assertInstanceOf(AddressDto::class, $user->address);
            $this->assertIsString($user->address->street);
            $this->assertIsInt($user->address->number);
        }
    }

    public function test_make_many_returns_multiple_instances(): void
    {
        $mocker = new DtoMocker();
        $many = $mocker->makeMany(UserDto::class, 3);

        $this->assertCount(3, $many);
        foreach ($many as $u) {
            $this->assertInstanceOf(UserDto::class, $u);
        }
    }

    public function test_constructor_injection_is_supported(): void
    {
        $mocker = new DtoMocker();
        $obj = $mocker->make(ConstructedDto::class);

        $this->assertInstanceOf(ConstructedDto::class, $obj);
        $this->assertIsString($obj->id);
        $this->assertIsInt($obj->value);
    }

    public function test_it_can_extend_with_custom_generator(): void
    {
        $mocker = new DtoMocker();
        $mocker->extend('string', fn() => 'fixed_string');

        $user = $mocker->make(UserDto::class);

        $this->assertStringStartsWith('fixed_string', $user->name);
    }
}