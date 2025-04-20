<?php

namespace A\Async;

class PromiseProxy extends Promise implements PromiseProxyInterface
{
    public function __call(string $name, array $arguments)
    {
        return $this->wait()->$name(...$arguments);
    }

    public function __get(string $name) : mixed
    {
        return $this->wait()->$name;
    }

    public function __set(string $name, $value) : void
    {
        $this->wait()->$name = $value;
    }

    public function __isset(string $name) : bool
    {
        return isset($this->wait()->$name);
    }

    public function __unset(string $name) : void
    {
        unset($this->wait()->$name);
    }
}
