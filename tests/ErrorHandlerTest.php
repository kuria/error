<?php

namespace Kuria\Error;

class ErrorHandlerTest extends \PHPUnit_Framework_TestCase
{
    /** @var ExceptionHandlerInterface|\PHPUnit_Framework_MockObject_MockObject */
    private $exceptionHandlerMock;
    /** @var TestErrorHandler|\PHPUnit_Framework_MockObject_MockObject */
    private $errorHandler;
    /** @var array[] */
    private $scheduledAssertions;

    protected function setUp()
    {
        $this->exceptionHandlerMock = $this->getMock(__NAMESPACE__ . '\\ExceptionHandlerInterface');

        $this->errorHandler = new TestErrorHandler($this->exceptionHandlerMock, 0);
        $this->errorHandler->setCleanBuffers(false);
        $this->errorHandler->setPrintUnhandledExceptionInDebug(false);
        $this->errorHandler->setWorkingDirectory(null);

        $this->scheduledAssertions = array();
    }

    public function testConfiguration()
    {
        $this->assertFalse($this->errorHandler->getDebug());

        /** @var ExceptionHandlerInterface $exceptionHandlerMock */
        $exceptionHandlerMock = $this->getMock(__NAMESPACE__ . '\\ExceptionHandlerInterface');

        $this->errorHandler->setDebug(true);
        $this->errorHandler->setWorkingDirectory(__DIR__);
        $this->errorHandler->setExceptionHandler($exceptionHandlerMock);
        $this->errorHandler->setCleanBuffers(false);
        $this->errorHandler->setPrintUnhandledExceptionInDebug(false);

        $this->assertTrue($this->errorHandler->getDebug());
        $this->assertSame($exceptionHandlerMock, $this->errorHandler->getExceptionHandler());
    }

    /**
     * @expectedException        ErrorException
     * @expectedExceptionMessage Something went wrong
     */
    public function testOnError()
    {
        $this->errorHandler->onError(E_USER_WARNING, 'Something went wrong', __FILE__, __LINE__);
    }

    public function testOnErrorSuppressed()
    {
        @$this->errorHandler->onError(E_USER_WARNING, 'Something went wrong', __FILE__, __LINE__);
    }

    public function testOnUncaughtException()
    {
        $exception = new \Exception();

        $this->exceptionHandlerMock->expects($this->once())
            ->method('handle')
            ->with(
                $this->identicalTo($exception),
                $this->identicalTo(ErrorHandler::UNCAUGHT_EXCEPTION)
            );

        $this->errorHandler->onUncaughtException($exception);
    }

    public function testDoesNotPrintUnhandledExceptionsWhenNotInDebugMode()
    {
        $this->errorHandler->setPrintUnhandledExceptionInDebug(true);

        $exceptionHandlerException = new \Exception('Exception handler exception');
        $uncaughtException = new \Exception('Uncaught exception');

        $this->exceptionHandlerMock->expects($this->once())
            ->method('handle')
            ->willThrowException($exceptionHandlerException);

        $this->expectOutputString('');

        $this->errorHandler->onUncaughtException($uncaughtException);
    }

    public function testPrintsUnhandlesExceptionsWhenInDebugMode()
    {
        $that = $this;

        $this->errorHandler->setDebug(true);
        $this->errorHandler->setPrintUnhandledExceptionInDebug(true);

        $exceptionHandlerException = new \Exception('Exception handler exception');
        $uncaughtException = new \Exception('Uncaught exception');

        $this->exceptionHandlerMock->expects($this->once())
            ->method('handle')
            ->willThrowException($exceptionHandlerException);

        $this->setOutputCallback(function ($output) use ($that) {
            $that->assertContains('Additonal exception was thrown while trying to call ', $output);
            $that->assertContains('Exception handler exception', $output);
            $that->assertContains('Uncaught exception', $output);
        });

        $this->errorHandler->onUncaughtException($uncaughtException);
    }

    public function testShutdownWithNoErrors()
    {
        $this->exceptionHandlerMock->expects($this->never())
            ->method('handle');

        $this->errorHandler->onShutdown();
    }

    public function testShutdownWithSuppressedError()
    {
        $this->exceptionHandlerMock->expects($this->never())
            ->method('handle');

        $this->simulateError();

        @$this->errorHandler->onError(E_USER_ERROR, 'Something went wrong', __FILE__, __LINE__);
        $this->errorHandler->onShutdown();
    }

    public function testShutdownWithHandledError()
    {
        $this->exceptionHandlerMock->expects($this->never())
            ->method('handle');

        $this->simulateError();

        try {
            $this->errorHandler->onError(E_USER_ERROR, 'Something went wrong', __FILE__, __LINE__);
        } catch (\ErrorException $e) {
        }

        $this->errorHandler->onShutdown();
    }

    public function testShutdownWithUnhandledError()
    {
        $this->exceptionHandlerMock->expects($this->once())
            ->method('handle')
            ->with(
                $this->isInstanceOf('ErrorException'),
                $this->identicalTo(ErrorHandler::FATAL_ERROR)
            );

        $this->simulateError();

        $this->errorHandler->onShutdown();
    }

    /**
     * @dataProvider provideOutOfMemoryErrorMessages
     * @param string $errorMessage
     */
    public function testShutdownWithOutOfMemoryError($errorMessage)
    {
        $this->exceptionHandlerMock->expects($this->once())
            ->method('handle')
            ->with(
                $this->isInstanceOf('ErrorException'),
                $this->identicalTo(ErrorHandler::OUT_OF_MEMORY)
            );

        $this->simulateError($errorMessage);

        $this->errorHandler->onShutdown();
    }

    /**
     * @return array[]
     */
    public function provideOutOfMemoryErrorMessages()
    {
        return array(
            array('Allowed memory size of 123456 bytes exhausted at 123:456 (tried to allocate 123456 bytes)'),
            array('Allowed memory size of %zu bytes exhausted (tried to allocate 123 bytes)'),
            array('Out of memory'),
            array('Out of memory (allocated 654231) at 123:456 (tried to allocate 123456 bytes)'),
            array('Out of memory (allocated 654321) (tried to allocate 123456 bytes)'),
        );
    }

    public function testShutdownWithErrorWhenActive()
    {
        $this->errorHandler->setAlwaysActive(false);

        $this->exceptionHandlerMock->expects($this->once())
            ->method('handle')
            ->with(
                $this->isInstanceOf('ErrorException'),
                $this->identicalTo(ErrorHandler::FATAL_ERROR)
            );

        $this->simulateError();

        set_error_handler(array($this->errorHandler, 'onError'));

        $e = null;
        try {
            $this->errorHandler->onShutdown();
        } catch (\Exception $e) {
        } catch (\Throwable $e) {
        }

        restore_error_handler();

        if ($e !== null) {
            throw $e;
        }
    }

    public function testOnShutdownWithErrorWhenNotActive()
    {
        $this->errorHandler->setAlwaysActive(false);

        $this->exceptionHandlerMock->expects($this->never())
            ->method('handle');

        $this->simulateError();

        $this->errorHandler->onShutdown();
    }

    public function testEvents()
    {
        $this->errorHandler->setDebug(true);

        $that = $this;

        $callCounters = array(
            'error' => 0,
            'suppressed_error' => 0,
            'exception' => 0,
        );

        // helper: assert listener call counts
        $assertCallCounts = function ($errorListenerCalls, $suppressedErrorListenerCalls, $exceptionListenerCalls) use ($that, &$callCounters) {
            $that->assertSame($errorListenerCalls, $callCounters['error'], 'expected error listener to be called n times');
            $that->assertSame($suppressedErrorListenerCalls, $callCounters['suppressed_error'], 'expected suppressed error listener to be called n times');
            $that->assertSame($exceptionListenerCalls, $callCounters['exception'], 'expected exception listener to be called n times');
        };

        // error listener
        $errorListener = function ($exception, $debug, $suppressed) use ($that, &$callCounters) {
            if (!$suppressed) {
                $that->scheduleAssertion('assertTrue', $debug, 'expected debug to be TRUE in error listener');
                $that->scheduleAssertion('assertInstanceOf', 'ErrorException', $exception, 'expected an instance of ErrorException in error listener');

                ++$callCounters['error'];
            }
        };

        // suppressed error listener
        $suppressedErrorListener = function ($exception, $debug, $suppressed) use ($that, &$callCounters) {
            if ($suppressed) {
                $that->scheduleAssertion('assertTrue', $debug, 'expected debug to be TRUE in suppressed error listener');
                $that->scheduleAssertion('assertInstanceOf', 'ErrorException', $exception, 'expected an instance of ErrorException in suppressed error listener');

                ++$callCounters['suppressed_error'];
            }
        };

        // exception error listener
        $exceptionListener = function ($exception, $debug, $errorType) use ($that, &$callCounters) {
            $that->scheduleAssertion('assertTrue', $debug, 'expected debug to be TRUE in exception listener');
            $that->scheduleAssertion('assertInstanceOf', 'Exception', $exception, 'expected an instance of Exception in exception listener');
            $that->scheduleAssertion('assertInternalType', 'integer', $errorType, 'expected error type to be an integer in exception listener');

            ++$callCounters['exception'];
        };

        // attach listeners
        $this->errorHandler
            ->on('error', $errorListener)
            ->on('error', $suppressedErrorListener)
            ->on('exception', $exceptionListener);

        // test
        $assertCallCounts(0, 0, 0);

        try {
            $this->errorHandler->onError(E_USER_ERROR, 'Test');
        } catch (\ErrorException $e) {
        }

        $assertCallCounts(1, 0, 0);

        try {
            @$this->errorHandler->onError(E_USER_ERROR, 'Test suppressed');
        } catch (\ErrorException $e) {
        }

        $assertCallCounts(1, 1, 0);

        try {
            $this->errorHandler->onUncaughtException(new \Exception('Test uncaught'));
        } catch (\ErrorException $e) {
        }

        $assertCallCounts(1, 1, 1);
    }

    public function testEventSuppressesErrorException()
    {
        $this->errorHandler->on('error', function ($exception, $debug, &$suppressed) {
            $suppressed = true;
        });

        // no exception should be thrown
        $this->errorHandler->onError(E_USER_ERROR, 'Test');
    }

    /**
     * @expectedException        ErrorException
     * @expectedExceptionMessage Test suppressed
     */
    public function testEventForcesErrorException()
    {
        $this->errorHandler->on('error', function ($exception, $debug, &$suppressed) {
            $suppressed = false;
        });

        // an exception should be thrown even if suppressed
        @$this->errorHandler->onError(E_USER_ERROR, 'Test suppressed');
    }

    public function testExceptionChainingWithErrorEvent()
    {
        $errorEventException = new \Exception('Event exception');

        $this->errorHandler->on('error', function () use ($errorEventException) {
            throw $errorEventException;
        });

        $thrownException = null;
        try {
            $this->errorHandler->onError(E_USER_ERROR, 'Test runtime');
        } catch (\Exception $thrownException) {
        }

        $this->assertNotNull($thrownException);
        $this->assertInstanceOf('RuntimeException', $thrownException);
        $this->assertSame('Additional exception was thrown from an [error] event listener. See previous exceptions.', $thrownException->getMessage());
        $this->assertSame($errorEventException, $thrownException->getPrevious());
        $this->assertInstanceOf('ErrorException', $errorEventException->getPrevious());
        $this->assertSame('Test runtime', $errorEventException->getPrevious()->getMessage());
    }

    public function testExceptionChainingWithExceptionEvent()
    {
        $that = $this;

        $uncaughtException = new \Exception('Test uncaught');
        $exceptionEventException = new \Exception('Event exception');

        $this->exceptionHandlerMock
            ->expects($this->once())
            ->method('handle')
            ->with(
                $this->logicalAnd(
                    $this->isInstanceOf('RuntimeException'),
                    $this->callback(function ($exception) use ($that, $uncaughtException, $exceptionEventException) {
                        $that->assertSame('Additional exception was thrown from an [exception] event listener. See previous exceptions.', $exception->getMessage());
                        $that->assertSame($exceptionEventException, $exception->getPrevious());
                        $that->assertSame($uncaughtException, $exceptionEventException->getPrevious());

                        return true;
                    })
                )
            );

        $this->errorHandler->on('exception', function () use ($exceptionEventException) {
            throw $exceptionEventException;
        });

        $this->errorHandler->onUncaughtException($uncaughtException);
    }

    public function testFailureEvent()
    {
        $that = $this;

        $uncaughtException = new \Exception('Test uncaught');
        $exceptionHandlerException = new \Exception('Exception handler exception');

        $failureListenerCalled = false;

        $this->errorHandler->on('failure', function ($exception, $debug, $errorType) use (
            $that,
            $exceptionHandlerException,
            $uncaughtException,
            &$failureListenerCalled
        ) {
            $failureListenerCalled = true;

            $that->scheduleAssertion('assertFalse', $debug, 'expected debug to be FALSE in failure listener');
            $that->scheduleAssertion('assertSame', ErrorHandler::UNCAUGHT_EXCEPTION, $errorType, 'expected error type to be UNCAUGHT_EXCEPTION');
            $that->scheduleAssertion('assertInstanceOf', 'RuntimeException', $exception, 'expected an instance of RuntimeException in failure listener');
            $that->scheduleAssertion('assertRegExp', '{Additonal exception was thrown while trying to call .*\. See previous exceptions\.}', $exception->getMessage(), 'expected an exception with correct message in failure listener');
            $that->scheduleAssertion('assertSame', $exceptionHandlerException, $exception->getPrevious(), 'expected the exception handler exception to be the previous exception in exception listener');
            $that->scheduleAssertion('assertSame', $uncaughtException, $exception->getPrevious()->getPrevious(), 'expected the original exception to be the last exception in the chain in exception listener');
        });

        $this->exceptionHandlerMock
            ->expects($this->once())
            ->method('handle')
            ->willThrowException($exceptionHandlerException);

        $this->errorHandler->onUncaughtException($uncaughtException);

        $this->assertTrue($failureListenerCalled, 'expected failure handler to be called');
    }

    /**
     * Schedule an assertion after the test
     *
     * @param string $method method name
     * @param mixed $args,...
     */
    public function scheduleAssertion($method)
    {
        $this->scheduledAssertions[] = array(
            'method' => $method,
            'args' => array_slice(func_get_args(), 1),
        );
    }

    protected function assertPostConditions()
    {
        foreach ($this->scheduledAssertions as $assertion) {
            call_user_func_array(array($this, $assertion['method']), $assertion['args']);
        }
    }

    /**
     * Simulate an error so error_get_last() works
     *
     * @param string $message
     * @param int    $type
     */
    private function simulateError($message = 'Simulated error', $type = E_USER_WARNING)
    {
        @trigger_error($message, $type);
    }
}

/**
 * @internal
 */
class TestErrorHandler extends ErrorHandler
{
    /** @var bool */
    private $alwaysActive = true;

    /**
     * @param bool $alwaysActive
     */
    public function setAlwaysActive($alwaysActive)
    {
        $this->alwaysActive = $alwaysActive;
    }

    protected function isActive()
    {
        return $this->alwaysActive || parent::isActive();
    }
}
