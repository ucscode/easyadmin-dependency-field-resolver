<?php

namespace Ucscode\EasyAdmin\FieldDependencyResolver\Dto;

class DataDto
{
    public function __construct(private array $data)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function all(): array
    {
        return $this->data;
    }

    public function remove(string $key): self
    {
        unset($this->data[$key]);
        return $this;
    }
}
