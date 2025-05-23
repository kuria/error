<?php declare(strict_types=1);

namespace Kuria\Error;

use Kuria\DevMeta\Test;
use Kuria\Error\Exception\ChainedException;
use Kuria\Error\Exception\ErrorException;
use Kuria\Error\Exception\FatalErrorException;
use Kuria\Error\Exception\OutOfMemoryException;
use PHPUnit\Framework\MockObject\MockObject;

class ErrorHandlerTest extends Test
{
    /** @var ErrorScreenInterface|MockObject */
    private $errorScreenMock;

    /** @var TestErrorHandler */
    private $errorHandler;

    /** @var array[] */
    private $scheduledAssertions;

    protected function setUp()
    {
        $this->errorScreenMock = $this->createMock(ErrorScreenInterface::class);

        $this->errorHandler = new TestErrorHandler($this->errorScreenMock, 0);
        $this->errorHandler->setCleanBuffers(false);
        $this->errorHandler->setPrintUnhandledExceptionInDebug(false);
        $this->errorHandler->setWorkingDirectory(null);

        $this->scheduledAssertions = [];
    }

    function testShouldConfigure()
    {
        $this->assertFalse($this->errorHandler->isDebugEnabled());

        /** @var ErrorScreenInterface $errorScreenMock */
        $errorScreenMock = $this->createMock(ErrorScreenInterface::class);

        $this->errorHandler->setDebug(true);
        $this->errorHandler->setWorkingDirectory(__DIR__);
        $this->errorHandler->setErrorScreen($errorScreenMock);
        $this->errorHandler->setCleanBuffers(false);
        $this->errorHandler->setPrintUnhandledExceptionInDebug(false);

        $this->assertTrue($this->errorHandler->isDebugEnabled());
        $this->assertSame($errorScreenMock, $this->errorHandler->getErrorScreen());
    }

    function testShouldHandleError()
    {
        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Something went wrong');

        $this->errorHandler->onError(E_USER_WARNING, 'Something went wrong', __FILE__, __LINE__);
    }

    function testShouldHandleSuppressedError()
    {
        @$this->errorHandler->onError(E_USER_WARNING, 'Something went wrong', __FILE__, __LINE__);

        $this->addToAssertionCount(1); // no exception was thrown => ok
    }

    function testShouldHandleUncaughtException()
    {
        $exception = new \Exception();

        $this->errorScreenMock->expects($this->once())
            ->method('render')
            ->with($this->identicalTo($exception));

        $this->errorHandler->onUncaughtException($exception);
        $this->assertSame(255, $this->errorHandler->getStopExecutionCode());
    }

    function testShouldNotPrintUnhandledExceptionsWhenNotInDebugMode()
    {
        $this->errorHandler->setPrintUnhandledExceptionInDebug(true);

        $errorScreenException = new \Exception('Error screen exception');
        $uncaughtException = new \Exception('Uncaught exception');

        $this->errorScreenMock->expects($this->once())
            ->method('render')
            ->willThrowException($errorScreenException);

        $this->expectOutputString('');

        $this->errorHandler->onUncaughtException($uncaughtException);
        $this->assertSame(255, $this->errorHandler->getStopExecutionCode());
    }

    function testShouldPrintsUnhandledExceptionsWhenInDebugMode()
    {
        $this->errorHandler->setDebug(true);
        $this->errorHandler->setPrintUnhandledExceptionInDebug(true);

        $errorScreenException = new \Exception('Error screen exception');
        $uncaughtException = new \Exception('Uncaught exception');

        $this->errorScreenMock->expects($this->once())
            ->method('render')
            ->willThrowException($errorScreenException);

        $this->setOutputCallback(function ($output) {
            $this->assertContains('Additional exception was thrown while trying to invoke ', $output);
            $this->assertContains('Error screen exception', $output);
            $this->assertContains('Uncaught exception', $output);
        });

        $this->errorHandler->onUncaughtException($uncaughtException);
        $this->assertSame(255, $this->errorHandler->getStopExecutionCode());
    }

    function testShouldHandleShutdownWithNoErrors()
    {
        $this->errorScreenMock->expects($this->never())
            ->method('render');

        $this->errorHandler->onShutdown();
    }

    function testShouldHandleShutdownWithSuppressedError()
    {
        $this->errorScreenMock->expects($this->never())
            ->method('render');

        $this->simulateError();

        @$this->errorHandler->onError(E_USER_ERROR, 'Something went wrong', __FILE__, __LINE__);
        $this->errorHandler->onShutdown();
    }

    function testShouldHandleShutdownWithHandledError()
    {
        $this->errorScreenMock->expects($this->never())
            ->method('render');

        $this->simulateError();

        try {
            $this->errorHandler->onError(E_USER_ERROR, 'Something went wrong', __FILE__, __LINE__);
        } catch (ErrorException $e) {
        }

        $this->errorHandler->onShutdown();
    }

    function testShouldHandleShutdownWithUnhandledError()
    {
        $this->errorScreenMock->expects($this->once())
            ->method('render')
            ->with($this->isInstanceOf(FatalErrorException::class));

        $this->simulateError();

        $this->errorHandler->onShutdown();
    }

    /**
     * @dataProvider provideOutOfMemoryErrorMessages
     */
    function testShouldHandleShutdownWithOutOfMemoryError(string $errorMessage)
    {
        $this->errorScreenMock->expects($this->once())
            ->method('render')
            ->with($this->isInstanceOf(OutOfMemoryException::class));

        $this->errorHandler->setOverrideErrorCodeInOomCheck(true); // cannot use trigger_error() with E_ERROR
        $this->simulateError($errorMessage);

        $this->errorHandler->onShutdown();
    }

    function provideOutOfMemoryErrorMessages()
    {
        return [
            ['Allowed memory size of 123456 bytes exhausted at 123:456 (tried to allocate 123456 bytes)'],
            ['Allowed memory size of %zu bytes exhausted (tried to allocate 123 bytes)'],
            ['Out of memory'],
            ['Out of memory (allocated 654231) at 123:456 (tried to allocate 123456 bytes)'],
            ['Out of memory (allocated 654321) (tried to allocate 123456 bytes)'],
        ];
    }

    function testShouldHandleShutdownWithErrorWhenActive()
    {
        $this->errorHandler->setAlwaysActive(false);

        $this->errorScreenMock->expects($this->once())
            ->method('render')
            ->with($this->isInstanceOf(FatalErrorException::class));

        $this->simulateError();

        set_error_handler([$this->errorHandler, 'onError']);

        try {
            $this->errorHandler->onShutdown();
        } finally {
            restore_error_handler();
        }
    }

    function testShouldHandleShutdownWithErrorWhenNotActive()
    {
        $this->errorHandler->setAlwaysActive(false);

        $this->errorScreenMock->expects($this->never())
            ->method('render');

        $this->simulateError();

        $this->errorHandler->onShutdown();
    }

    function testShouldEmitEvents()
    {
        $this->errorHandler->setDebug(true);

        $callCounters = [
            'error' => 0,
            'suppressed_error' => 0,
            'exception' => 0,
        ];

        // helper: assert listener call counts
        $assertCallCounts = function ($errorListenerCalls, $suppressedErrorListenerCalls, $exceptionListenerCalls) use (&$callCounters) {
            $this->assertSame($errorListenerCalls, $callCounters['error']);
            $this->assertSame($suppressedErrorListenerCalls, $callCounters['suppressed_error']);
            $this->assertSame($exceptionListenerCalls, $callCounters['exception']);
        };

        // error listener
        $errorListener = function (ErrorException $exception, bool $debug) use (&$callCounters) {
            $this->scheduleAssertion('assertTrue', $debug, 'Expected debug to be TRUE in error listener');

            ++$callCounters[$exception->isSuppressed() ? 'suppressed_error' : 'error'];
        };

        // exception error listener
        $exceptionListener = function (\Throwable $exception, bool $debug) use (&$callCounters) {
            $this->scheduleAssertion('assertTrue', $debug, 'Expected debug to be TRUE in exception listener');

            ++$callCounters['exception'];
        };

        // attach listeners
        $this->errorHandler->on(ErrorHandlerEvents::ERROR, $errorListener);
        $this->errorHandler->on(ErrorHandlerEvents::EXCEPTION, $exceptionListener);

        // test
        $assertCallCounts(0, 0, 0);

        try {
            $this->errorHandler->onError(E_USER_ERROR, 'Test');
        } catch (ErrorException $e) {
        }

        $assertCallCounts(1, 0, 0);

        try {
            @$this->errorHandler->onError(E_USER_ERROR, 'Test suppressed');
        } catch (ErrorException $e) {
        }

        $assertCallCounts(1, 1, 0);

        $this->errorHandler->onUncaughtException(new \Exception('Test uncaught'));
        $this->assertSame(255, $this->errorHandler->getStopExecutionCode());

        $assertCallCounts(1, 1, 1);
    }

    function testEventShouldBeAbleToSuppressErrorException()
    {
        $this->errorHandler->on(ErrorHandlerEvents::ERROR, function (ErrorException $exception) {
            $exception->suppress();
        });

        $this->errorHandler->onError(E_USER_ERROR, 'Test');

        $this->addToAssertionCount(1); // no exception was thrown => ok
    }

    function testEventShouldBeAbleToForceErrorException()
    {
        $this->errorHandler->on(ErrorHandlerEvents::ERROR, function (ErrorException $exception) {
            $exception->force();
        });

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Test suppressed');

        // an exception should be thrown even if suppressed
        @$this->errorHandler->onError(E_USER_ERROR, 'Test suppressed');
    }

    function testShouldChainExceptionsFromErrorEventHandlers()
    {
        $errorEventException = new \Exception('Event exception');

        $this->errorHandler->on(ErrorHandlerEvents::ERROR, function () use ($errorEventException) {
            throw $errorEventException;
        });

        $thrownException = null;
        try {
            $this->errorHandler->onError(E_USER_ERROR, 'Test runtime');
        } catch (\Throwable $thrownException) {
        }

        $this->assertNotNull($thrownException);
        $this->assertInstanceOf(ChainedException::class, $thrownException);
        $this->assertSame('Additional exception was thrown from an [error] event listener. See previous exceptions.', $thrownException->getMessage());
        $this->assertSame($errorEventException, $thrownException->getPrevious());
        $this->assertInstanceOf(ErrorException::class, $errorEventException->getPrevious());
        $this->assertSame('Test runtime', $errorEventException->getPrevious()->getMessage());
    }

    function testShouldChainExceptionsFromExceptionEventHandlers()
    {
        $uncaughtException = new \Exception('Test uncaught');
        $exceptionEventException = new \Exception('Event exception');

        $this->errorScreenMock
            ->expects($this->once())
            ->method('render')
            ->with(
                $this->logicalAnd(
                    $this->isInstanceOf(ChainedException::class),
                    $this->callback(function (\Throwable $exception) use ($uncaughtException, $exceptionEventException) {
                        $this->assertSame(
                            'Additional exception was thrown from an [exception] event listener. See previous exceptions.',
                            $exception->getMessage()
                        );
                        $this->assertSame($exceptionEventException, $exception->getPrevious());
                        $this->assertSame($uncaughtException, $exceptionEventException->getPrevious());

                        return true;
                    })
                )
            );

        $this->errorHandler->on(ErrorHandlerEvents::EXCEPTION, function () use ($exceptionEventException) {
            throw $exceptionEventException;
        });

        $this->errorHandler->onUncaughtException($uncaughtException);
        $this->assertSame(255, $this->errorHandler->getStopExecutionCode());
    }

    function testShouldEmitFailureEvent()
    {
        $uncaughtException = new \Exception('Test uncaught');
        $errorScreenException = new \Exception('Error screen exception');

        $failureListenerCalled = false;

        $this->errorHandler->on(ErrorHandlerEvents::FAILURE, function (
            \Throwable $exception,
            bool $debug
        ) use (
            $uncaughtException,
            $errorScreenException,
            &$failureListenerCalled
        ) {
            $failureListenerCalled = true;

            $this->scheduleAssertion(
                'assertFalse',
                $debug,
                'Expected debug to be FALSE in failure listener'
            );
            $this->scheduleAssertion(
                'assertInstanceOf',
                ChainedException::class,
                $exception,
                'Expected an instance of RuntimeException in failure listener'
            );
            $this->scheduleAssertion(
                'assertRegExp',
                '{Additional exception was thrown while trying to invoke .*\. See previous exceptions\.}',
                $exception->getMessage(),
                'Expected an exception with correct message in failure listener'
            );
            $this->scheduleAssertion(
                'assertSame',
                $errorScreenException,
                $exception->getPrevious(),
                'Expected the error screen exception to be the previous exception in exception listener'
            );
            $this->scheduleAssertion(
                'assertSame',
                $uncaughtException,
                $exception->getPrevious()->getPrevious(),
                'Expected the original exception to be the last exception in the chain in exception listener'
            );
        });

        $this->errorScreenMock
            ->expects($this->once())
            ->method('render')
            ->willThrowException($errorScreenException);

        $this->errorHandler->onUncaughtException($uncaughtException);

        $this->assertTrue($failureListenerCalled, 'Expected failure handler to be called');
        $this->assertSame(255, $this->errorHandler->getStopExecutionCode());
    }

    /**
     * Schedule an assertion after the test
     */
    function scheduleAssertion(string $method, ...$args): void
    {
        $this->scheduledAssertions[] = [
            'method' => $method,
            'args' => $args,
        ];
    }

    protected function assertPostConditions()
    {
        foreach ($this->scheduledAssertions as $assertion) {
            $this->{$assertion['method']}(...$assertion['args']);
        }
    }

    /**
     * Simulate an error so error_get_last() works
     */
    private function simulateError(string $message = 'Simulated error'): void
    {
        @trigger_error($message, E_USER_WARNING);
    }
}

/**
 * @internal
 */
class TestErrorHandler extends ErrorHandler
{
    protected const FATAL_SHUTDOWN_ERRORS = [E_ERROR => true, E_CORE_ERROR => true, E_COMPILE_ERROR => true, E_USER_WARNING => true];

    /** @var bool */
    private $alwaysActive = true;

    /** @var bool */
    private $overrideErrorCodeInOomCheck = false;

    /** @var int|null */
    private $stopExecutionCode;

    function setAlwaysActive(bool $alwaysActive): void
    {
        $this->alwaysActive = $alwaysActive;
    }

    function setOverrideErrorCodeInOomCheck(bool $overrideErrorCodeInOomCheck): void
    {
        $this->overrideErrorCodeInOomCheck = $overrideErrorCodeInOomCheck;
    }

    public function getStopExecutionCode(): ?int
    {
        return $this->stopExecutionCode;
    }

    protected function isActive(): bool
    {
        return $this->alwaysActive || parent::isActive();
    }

    protected function isOutOfMemoryError(array $error): bool
    {
        if ($this->overrideErrorCodeInOomCheck) {
            $error['type'] = E_ERROR;
        }

        return parent::isOutOfMemoryError($error);
    }

    protected function stopExecution(int $code): void
    {
        $this->stopExecutionCode = $code;
    }
}
