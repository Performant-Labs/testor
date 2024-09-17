<?php

namespace PL\Tests\Robo\Task\Testor\Helper;

use phpmock\Deactivatable;
use PHPUnit\Event\Runtime\PHPUnit;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
use PL\Tests\Robo\Task\Testor\TestorTestCase;
use ReflectionFunction;

class MockBuiltIn implements Deactivatable
{
    // How to mock built-in functions?
    // opt #1. php-mock + php-mock-phpunit - doesn't handle expectations for multiple calls.
    // opt #2. php-mock + php-mock-mockery - handles multiple calls but
    // assertion messages doesn't contain any information about wat's wrong.
    // opt #3. php-mock + php-mock-phpunit + custom expectation check.
    // That is what we are doing here.
    //
    //
    private \PHPUnit\Framework\MockObject\MockObject|\phpmock\phpunit\MockObjectProxy $mock;
    private \PHPUnit\Framework\MockObject\Builder\InvocationMocker $invocationMocker;
    private array $returnMap = [];
    private string $funcName;
    private array $funcArgNames;
    /**
     * @var bool if assert has been already done
     */
    private static bool $ad = false;

    /**
     * @param $mock
     * @param $function
     * @throws \ReflectionException
     */
    public function __construct($mock, $function)
    {
        $this->mock = $mock;

        // Save function name and argument names for the better reporting.
        $this->funcName = $function;
        $f = new ReflectionFunction($function);
        $result = array();
        foreach ($f->getParameters() as $param) {
            $result[] = $param->name;
        }
        $this->funcArgNames = $result;

        // Reset assertion-done flag on any new mock creation
        // (this seems to be a lesser evil now...)
        self::$ad = false;
    }

    public function expects(InvocationOrder $invocationRule): static
    {
        $this->invocationMocker = $this->mock->expects($invocationRule);
        return $this;
    }

    // Now proxy InvocationMocker and add other useful methods that needed.

    public function will($stub)
    {
        $this->invocationMocker->will($stub);
        return $this;
    }

    public function willReturn(mixed $value, mixed...$nextValues): static
    {
        $this->invocationMocker->willReturn($value, ...$nextValues);
        return $this;
    }

    public function willReturnReference(mixed &$reference): static
    {
        $this->invocationMocker->willReturnReference($reference);
        return $this;
    }

    public function willReturnMap(array $map): static
    {
        $this->invocationMocker->willReturnMap($map);
        return $this;
    }

    public function willReturnArgument(int $argumentIndex): static
    {
        $this->invocationMocker->willReturnArgument($argumentIndex);
        return $this;
    }

    public function willReturnCallback(callable $callback): static
    {
        $this->invocationMocker->willReturnCallback($callback);
        return $this;
    }

    public function willReturnSelf(): static
    {
        $this->invocationMocker->willReturnSelf();
        return $this;
    }

    public function willReturnOnConsecutiveCalls(mixed ...$values): static
    {
        $this->invocationMocker->willReturnOnConsecutiveCalls(...$values);
        return $this;
    }

    public function willThrowException(\Throwable $exception): static
    {
        $this->invocationMocker->willThrowException($exception);
        return $this;
    }

    public function after(string $id): static
    {
        $this->invocationMocker->after($id);
        return $this;
    }

    public function with(mixed ...$args): static
    {
        $this->invocationMocker->with(...$args);
        return $this;
    }

    public function withAnyParameters(): static
    {
        $this->invocationMocker->withAnyParameters();
        return $this;
    }

    /** Will return values based on arguments from a map but do checks.
     *
     * Same as {@link MockBuiltIn::willReturnMap()} but
     * will check argument and fail if they don't match...
     * @param array $map
     * @return self
     */
    public function withReturnMap(array $map): static
    {
        $this->returnMap = $map;
        $this->willReturnCallback(function (...$args) {
            // Filter out what mocker put here (maybe there is a better way...)
            $args = array_filter($args, fn($value) => $value != 'optionalParameter');

            // Special case #1: an unexpected call
            if (count($this->returnMap) == 0) {
                $argToStr = implode(', ', $args);
                self::$ad = true;
                Assert::fail("Call $this->funcName($argToStr) is unexpected");
            }

            $numberOfParameters = count($args);
            // At point 0 all possible args are match.

            // PHP will copy an array.
            $mapmatch = $this->returnMap;
            foreach (range(0, $numberOfParameters - 1) as $i) {
                $newmapmatch = [];
                foreach ($mapmatch as $match) {
                    if ($match[$i] == $args[$i]) {
                        $newmapmatch[] = $match;
                    }
                }

                // if no match for argument $i,
                // assert the difference
                if (count($newmapmatch) == 0) {
                    // Take the first match from the [0..$i-1] arguments
                    $match = $mapmatch[0];
                    self::$ad = true;
                    Assert::assertEquals($match[$i], $args[$i], "Argument '{$this->funcArgNames[$i]}' of '$this->funcName' does not match.");
                }

                $mapmatch = $newmapmatch;
            }

            // Remove matched args from the expectations
            $match = $mapmatch[0];
            $i = array_search($match, $this->returnMap);
            array_splice($this->returnMap, $i, 1);

            // Last element of the map is return value.
            return $match[$numberOfParameters];
        });
        return $this;
    }

    public function disable(): void
    {
//        TODO this will be reported as internal PHPUnit error.
// So far disable reporting missing calls at all, because they'll be reported
// by count expectation any way. Further we may reconsider it...
        // If we've done assertion before **in any of MockBuiltIn instances**
        // we mustn't override it here.
//        if (!self::$ad) {
//            Assert::assertEquals([], $this->returnMap, "Missing calls of '$this->funcName'");
//        }
    }
}