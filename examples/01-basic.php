<?php

require __DIR__ . '/../vendor/autoload.php';

new \A\Async\Promise(function () {
    echo 'Step 1 - fetching' . PHP_EOL;

    return file_get_contents('https://api.ipify.org?format=json');
})->then(function ($result) {
    echo '2 - json_decode' . PHP_EOL;

    return json_decode($result);
})->then(function ($data) {
    echo '3 - print' . PHP_EOL;

    var_dump($data->ip);
})->catch(function ($e) {
    echo 'E - exception' . PHP_EOL;

    var_dump($e);
})->finally(function () {
    echo 'F - finally' . PHP_EOL;
});

echo 'Hello world...' . PHP_EOL; // This will be printed before the promise is resolved
