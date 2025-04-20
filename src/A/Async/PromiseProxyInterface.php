<?php

namespace A\Async;

interface PromiseProxyInterface extends PromiseInterface
{
    public function __call(string $name, array $arguments);

    public function __get(string $name) : mixed;

    public function __set(string $name, $value) : void;

    public function __isset(string $name) : bool;

    public function __unset(string $name) : void;
}
