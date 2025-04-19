<?php

namespace A\Async;

class Promise implements \A\Async\PromiseInterface
{
    protected const PENDING   = 0; // Initial state, neither fulfilled nor rejected
    protected const FULFILLED = 1; // Meaning that the operation was completed successfully
    protected const REJECTED  = 2; // Meaning that the operation failed

    /**
     * @var int $id Unique identifier each instance
     */
    protected readonly int $id;

    /**
     * @var int $state Current state of the promise
     */
    protected int $state = self::PENDING;

    /**
     * @var \Fiber $fiber Fiber instance
     */
    protected \Fiber $fiber;

    /**
     * @var mixed $result Result of the promise
     */
    protected mixed $result = null;

    /**
     * @var static[]
     */
    protected array $reasons = [];

    /**
     * @var bool $is_pending True if the promise pending otherwise false
     */
    protected bool $is_pending {
        get => $this->state === self::PENDING;
    }

    /**
     * @var bool $is_fulfilled True if the promise fulfilled otherwise false
     */
    protected bool $is_fulfilled {
        get => $this->state === self::FULFILLED;
    }

    /**
     * @var bool $is_rejected True if the promise rejected otherwise false
     */
    protected bool $is_rejected {
        get => $this->state === self::REJECTED;
    }

    /**
     * @var bool $is_settled True if the promise settled otherwise false
     */
    protected bool $is_settled {
        get => $this->state !== self::PENDING;
    }

    /**
     * @var static[] $pool Scheduler pool of promises
     */
    protected static array $pool = [];

    /**
     * @param callable $callback Callback function to be executed asynchronously
     * @throws \ReflectionException If the given callback method does not exist
     * @throws \InvalidArgumentException If the given callback has a mandatory parameter
     */
    public function __construct(callable $callback)
    {
        $reflection = is_array($callback) ? new \ReflectionMethod($callback[0], $callback[1]) : new \ReflectionFunction($callback);

        foreach ($reflection->getParameters() as $param)
        {
            if ($param->isOptional() === false) // Mandatory parameter
            {
                throw new \InvalidArgumentException('Promise callback must not have mandatory parameters');
            }
        }

        $this->id = spl_object_id($this);

        $this->fiber = new \Fiber(function () use ($callback) {
            $result = $callback();

            return $result instanceof \Generator ? static::drain($result) : $result;
        });

        self::$pool[$this->id] = $this;
    }

    protected function depend(self $parent) : self
    {
        if ($parent->is_pending)
        {
            $parent->reasons[] = $this;
            unset(self::$pool[$this->id]);
        }

        return $this;
    }

    protected function settle()
    {
        if ($this->is_pending and $this->fiber->isRunning() === false)
        {
            try
            {
                if ($this->fiber->isSuspended())
                {
                    $this->fiber->resume();
                }
                else if ($this->fiber->isStarted() === false)
                {
                    $this->fiber->start();
                }
                else if ($this->fiber->isTerminated())
                {
                    $this->resolve($this->fiber->getReturn());
                }
                else
                {
                    throw new \RuntimeException('Promise fiber is in an unknown state');
                }
            }
            catch (\Throwable $exception)
            {
                $this->reject($exception);
            }
        }
    }

    protected function resolve(mixed $result)
    {
        if ($this->is_pending)
        {
            $this->result = $result;
            $this->state  = self::FULFILLED;

            foreach ($this->reasons as $_ => $reason)
            {
                static::$pool[$reason->id] = $reason;
                unset($this->reasons[$_]);
            }

            unset(self::$pool[$this->id]);
        }
    }

    protected function reject(\Throwable $exception)
    {
        if ($this->is_pending)
        {
            $this->result = $exception;
            $this->state  = self::REJECTED;

            unset(self::$pool[$this->id]);

            if (count($this->reasons))
            {
                foreach ($this->reasons as $_ => $reason)
                {
                    static::$pool[$reason->id] = $reason;
                    unset($this->reasons[$_]);
                }
            }
            else if ($exception instanceof \Throwable)
            {
                throw $exception;
            }
        }
    }

    public function then(callable $on_fulfilled) : \A\Async\PromiseInterface
    {
        return new static(function () use ($on_fulfilled) {
            // Await previous promise to be settled
            while ($this->is_pending)
            {
                \Fiber::getCurrent()?->suspend();
            }

            return $this->is_fulfilled ? $on_fulfilled($this->result) : throw $this->result;
        })->depend($this);
    }

    public function catch(callable $on_rejected, string $exception_classname = \Throwable::class) : \A\Async\PromiseInterface
    {
        return new static(function () use ($on_rejected, $exception_classname) {
            // Await previous promise to be settled
            while ($this->is_pending)
            {
                \Fiber::getCurrent()?->suspend();
            }

            if ($this->is_rejected)
            {
                if ($this->result instanceof $exception_classname)
                {
                    return $on_rejected($this->result);
                }
                else
                {
                    throw $this->result;
                }
            }

            return $this->result;
        })->depend($this);
    }

    public function finally(callable $on_finally) : \A\Async\PromiseInterface
    {
        return new static(function () use ($on_finally) {
            // Await previous promise to be settled
            while (!$this->is_settled)
            {
                \Fiber::getCurrent()?->suspend();
            }

            return $on_finally($this->result);
        })->depend($this);
    }

    public function wait() : mixed
    {
        while ($this->is_pending)
        {
            \Fiber::getCurrent()?->suspend();
        }

        return $this->result;
    }

    public static function drain(\Generator $generator)
    {
        foreach ($generator as $value)
        {
            if ($value instanceof \Generator)
            {
                static::drain($value);
            }
            else if ($value instanceof Promise)
            {
                $value->wait();
            }
            else
            {
                \Fiber::getCurrent()?->suspend($value);
            }
        }

        return $generator->getReturn();
    }

    public static function all(self ...$promises) : static
    {
        return new static(function () use ($promises) {
            $results = [];
            foreach ($promises as $promise)
            {
                $results[] = $promise->wait();
            }
            return $results;
        });
    }

    public static function race(self ...$promises) : static
    {
        return new static(function () use ($promises) {
            while (count($promises))
            {
                foreach ($promises as $promise)
                {
                    if ($promise->is_settled)
                    {
                        return $promise->result;
                    }

                    \Fiber::getCurrent()?->suspend();
                }
            }

            throw new \RuntimeException('No promises');
        });
    }

    public static function schedule($untilAllSettled = true)
    {
        do
        {
            foreach (static::$pool as $id => $promise)
            {
                if ($promise->is_pending)
                {
                    $promise->settle();
                }
                else
                {
                    unset(static::$pool[$id]);
                }
            }

            usleep(10*1000);
        } while ($untilAllSettled and static::$pool);
    }
}

register_shutdown_function('A\Async\Promise::schedule');
