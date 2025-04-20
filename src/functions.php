<?php

if (!function_exists('asleep'))
{
    function asleep(float $seconds = 0) : void
    {
        if ($fiber = \Fiber::getCurrent())
        {
            for ($until = hrtime(true) + ceil($seconds * 1000 * 1000 * 1000) ; hrtime(true) < $until ; )
            {
                $fiber->suspend();
            }
        }
        else
        {
            $microseconds = ceil($seconds * 1000 * 1000);

            usleep($microseconds);
        }
    }
}

if (!function_exists('await'))
{
    function await(\A\Async\PromiseInterface $promise)
    {
    }
}
