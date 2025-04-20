<?php

require __DIR__ . '/../vendor/autoload.php';

function getUser(int $id) : \A\Async\PromiseProxyInterface
{
    return new \A\Async\PromiseProxy(function () use ($id) {
        asleep(1); // Simulate a delay of 1 second

        return (object) [
            'name' => $id == 1 ? 'John Doe' : 'Random User',
            'age' => $id == 1 ? 18 : 25,
        ];
    });
}

// Promise Classic

getUser(1)->then(function ($user) {
    echo "[classic] {$user->name} is {$user->age} years old !" . PHP_EOL;
});

// Promise Proxy (not need to explicitly call await())

$user = getUser(2);

echo "[proxy] {$user->name} is {$user->age} years old !" . PHP_EOL;
