<?php

namespace A\Async;

interface PromiseInterface
{
    /**
     * Register a callback executed when the promise is successfully fulfilled.
     */
    public function then(callable $on_fulfilled) : \A\Async\PromiseInterface;

    /**
     * Register a callback executed when the promise is rejected with a specific (or default) exception.
     */
    public function catch(callable $on_rejected, string $exception_classname = \Throwable::class) : \A\Async\PromiseInterface;

    /**
     * Register a callback executed whether the promise is fulfilled or rejected.
     */
    public function finally(callable $on_finally) : \A\Async\PromiseInterface;

    /**
     * Block execution until the promise settles and return its resolved value.
     */
    public function wait() : mixed;
}
