<?php

namespace Unit;

use PHPUnit\Framework\TestCase;
use A\Async\Promise;

class PromiseTest extends TestCase
{
    public function testResolvesCallbackResultWhenFulfilled()
    {
        $promise = new Promise(fn() => 42);
        Promise::schedule();
        $this->assertEquals(42, $promise->wait());
    }

    public function testRejectsWithExceptionWhenCallbackThrows()
    {
        $promise = new Promise(fn() => throw new \RuntimeException('Error'));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error');
        Promise::schedule();
        $promise->wait();
    }

    public function testChainsThenCallbacksCorrectly()
    {
        $promise = new Promise(fn() => 10);
        $chained = $promise->then(fn($result) => $result * 2);
        Promise::schedule();
        $this->assertEquals(20, $chained->wait());
    }

    public function testHandlesCatchForSpecificException()
    {
        $promise = new Promise(fn() => throw new \InvalidArgumentException('Invalid'));
        $caught = $promise->catch(fn($e) => 'Caught', \InvalidArgumentException::class);
        Promise::schedule();
        $this->assertEquals('Caught', $caught->wait());
    }

    public function testFinallyCallbackExecutesAfterSettlement()
    {
        $promise = new Promise(fn() => 5);
        $finalized = $promise->finally(fn($result) => $result + 1);
        Promise::schedule();
        $this->assertEquals(6, $finalized->wait());
    }

    public function testAllResolvesWhenAllPromisesFulfilled()
    {
        $promise1 = new Promise(fn() => 1);
        $promise2 = new Promise(fn() => 2);
        $all = Promise::all($promise1, $promise2);
        Promise::schedule();
        $this->assertEquals([1, 2], $all->wait());
    }

    public function testRaceResolvesWithFirstSettledPromise()
    {
        $promise1 = new Promise(fn() => 1);
        $promise2 = new Promise(fn() => 2);
        $race = Promise::race($promise1, $promise2);
        Promise::schedule();
        $this->assertEquals(1, $race->wait());
    }

    public function testRejectsUnhandledExceptionInCatch()
    {
        $promise = new Promise(fn() => throw new \RuntimeException('Unhandled'));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unhandled');
        $promise->catch(fn($e) => null, \InvalidArgumentException::class);
        Promise::schedule();
        $promise->wait();
    }






    public function testResolvesWithNullWhenCallbackReturnsNothing()
    {
        $promise = new Promise(fn() => null);
        Promise::schedule();
        $this->assertNull($promise->wait());
    }

    public function testRejectsWithCustomException()
    {
        $promise = new Promise(fn() => throw new \DomainException('Custom error'));
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Custom error');
        Promise::schedule();
        $promise->wait();
    }

    public function testHandlesEmptyArrayInAll()
    {
        $all = Promise::all();
        Promise::schedule();
        $this->assertEquals([], $all->wait());
    }

    public function testHandlesEmptyArrayInRace()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No promises provided');
        Promise::race();
        Promise::schedule();
    }

    public function testHandlesNestedPromisesInAll()
    {
        $promise1 = new Promise(fn() => new Promise(fn() => 1));
        $promise2 = new Promise(fn() => 2);
        $all = Promise::all($promise1, $promise2);
        Promise::schedule();
        $this->assertEquals([1, 2], $all->wait());
    }

    public function testHandlesNestedPromisesInRace()
    {
        $promise1 = new Promise(fn() => new Promise(fn() => 1));
        $promise2 = new Promise(fn() => 2);
        $race = Promise::race($promise1, $promise2);
        Promise::schedule();
        $this->assertEquals(1, $race->wait());
    }

    public function testFinallyCallbackExecutesOnRejection()
    {
        $promise = new Promise(fn() => throw new \RuntimeException('Error'));
        $finalized = $promise->finally(fn() => 'Finalized');
        Promise::schedule();
        $this->assertEquals('Finalized', $finalized->wait());
    }
}
