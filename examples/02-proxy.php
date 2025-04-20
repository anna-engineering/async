<?php

require __DIR__ . '/../vendor/autoload.php';

$promise = new \A\Async\Promise(function () {
    sleep(2);

    return (object) [
        'name' => 'John Doe',
        'age' => 30,
    ];
});

echo 'Hello world...' . PHP_EOL; // This will be printed before the promise is resolved

echo $promise->name;
