<?php

namespace A\Async;

interface PromiseInterface
{
    public function then(callable $on_fulfilled) : \A\Async\PromiseInterface;

    public function catch(callable $on_rejected, string $exception_classname = \Throwable::class) : \A\Async\PromiseInterface;

    public function finally(callable $on_finally) : \A\Async\PromiseInterface;

    public function wait() : mixed;
}
