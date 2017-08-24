<?php declare(strict_types=1);

namespace Kuria\Error\Exception;

use PHPUnit\Framework\TestCase;

class ErrorExceptionTest extends TestCase
{
    function testMinimialConstructorCall()
    {
        $e = new ErrorException();

        $this->assertSame('', $e->getMessage());
        $this->assertSame(E_ERROR, $e->getSeverity());
        $this->assertSame(0, $e->getCode());
        $this->assertFalse($e->isSuppressed());
        $this->assertContains('ErrorException.php', $e->getFile());
        $this->assertGreaterThan(0, $e->getLine());
        $this->assertNull($e->getPrevious());
    }

    function testFullConstructorCall()
    {
        $previous = new \Exception('Previous exception');

        $e = new ErrorException(
            'Test message',
            E_USER_WARNING,
            true,
            'example.php',
            123,
            $previous
        );

        $this->assertSame('Test message', $e->getMessage());
        $this->assertSame(E_USER_WARNING, $e->getSeverity());
        $this->assertSame(0, $e->getCode());
        $this->assertTrue($e->isSuppressed());
        $this->assertSame('example.php', $e->getFile());
        $this->assertSame(123, $e->getLine());
        $this->assertSame($previous, $e->getPrevious());
    }

    function testSuppress()
    {
        $e = new ErrorException('Test message', E_USER_WARNING, false);

        $this->assertFalse($e->isSuppressed());

        $e->suppress();

        $this->assertTrue($e->isSuppressed());
    }

    function testForce()
    {
        $e = new ErrorException('Test message', E_USER_WARNING, true);

        $this->assertTrue($e->isSuppressed());

        $e->force();

        $this->assertFalse($e->isSuppressed());
    }
}
