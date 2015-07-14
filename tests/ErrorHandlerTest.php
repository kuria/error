<?php

namespace Kuria\Error;

use Kuria\Error\FatalErrorHandlerInterface;

class ErrorHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testConfig()
    {
        $handler = $this->getErrorHandlerMock();

        $handler->setDebug(true);
        $handler->setCwd(__DIR__);
        $handler->setFatalHandler();
    }

    /**
     * @expectedException        ErrorException
     * @expectedExceptionMessage Something went wrong
     */
    public function testOnError()
    {
        $handler = $this->getErrorHandlerMock();

        $handler->onError(E_USER_WARNING, 'Something went wrong', __FILE__, __LINE__);
    }

    /**
     * @expectedException        Kuria\Error\ContextualErrorException
     * @expectedExceptionMessage Something went wrong
     */
    public function testOnErrorWithContext()
    {
        $handler = $this->getErrorHandlerMock();

        $handler->onError(E_USER_WARNING, 'Something went wrong', __FILE__, __LINE__, array('foo' => 'bar'));
    }

    public function testOnErrorSuppressed()
    {
        $handler = $this->getErrorHandlerMock();

        @$handler->onError(E_USER_WARNING, 'Something went wrong', __FILE__, __LINE__);
    }

    public function testOnException()
    {
        $handler = $this->getErrorHandlerMock();

        $handler
            ->expects($this->once())
            ->method('handleFatal')
        ;

        $handler->onException(new \Exception('Boo'));
    }

    public function testOnExceptionDoesNotPrintOnEmergency()
    {
        $this->expectOutputString('');

        $handler = $this->getErrorHandlerMock();

        $emergencyException = new \Exception('Emergency exception');
        $fatalException = new \Exception('Test fatal');

        $handler
            ->expects($this->once())
            ->method('handleFatal')
            ->willReturnCallback(function () use ($emergencyException) {
                throw $emergencyException;
            })
        ;

        $handler->onException($fatalException);
    }

    public function testOnExceptionPrintsOnEmergencyInDebug()
    {
        $this->expectOutputRegex('~Test fatal.*Emergency exception~s');

        $handler = $this->getErrorHandlerMock();

        $handler->setDebug(true);

        $emergencyException = new \Exception('Emergency exception');
        $fatalException = new \Exception('Test fatal');

        $handler
            ->expects($this->once())
            ->method('handleFatal')
            ->willReturnCallback(function () use ($emergencyException) {
                throw $emergencyException;
            })
        ;

        $handler->onException($fatalException);
    }

    public function testOnShutdownWithNoErrors()
    {
        $handler = $this->getErrorHandlerMock();

        $handler
            ->expects($this->never())
            ->method('handleFatal')
        ;

        $handler->onShutdown();
    }

    public function testOnShutdownWithSuppressedError()
    {
        $handler = $this->getErrorHandlerMock();

        $handler
            ->expects($this->never())
            ->method('handleFatal')
        ;

        @strlen(); // simulate error for error_get_last() to work

        @$handler->onError(E_USER_ERROR, 'Something went wrong', __FILE__, __LINE__);

        $handler->onShutdown();
    }

    public function testOnShutdownWithHandledError()
    {
        $handler = $this->getErrorHandlerMock();

        $handler
            ->expects($this->never())
            ->method('handleFatal')
        ;

        @strlen(); // simulate error for error_get_last() to work

        try {
            $handler->onError(E_USER_ERROR, 'Something went wrong', __FILE__, __LINE__);
        } catch (\ErrorException $e) {
        }

        $handler->onShutdown();
    }

    public function testOnShutdownWithUnhandledError()
    {
        $handler = $this->getErrorHandlerMock();

        $handler
            ->expects($this->once())
            ->method('handleFatal')
        ;

        @strlen(); // simulate error for error_get_last() to work

        $handler->onShutdown();
    }

    public function testOnShutdownWhenActive()
    {
        $handler = $this->getMock(__NAMESPACE__ . '\ErrorHandler', array(
            'handleFatal',
            'onException'
        ));

        $handler
            ->expects($this->once())
            ->method('onException')
        ;

        @strlen(); // simulate error for error_get_last() to work

        set_error_handler(array($handler, 'onError'));

        try {
            $handler->onShutdown();
        } catch (\Exception $e) {
            restore_error_handler();

            throw $e;
        }
    }

    public function testOnShutdownWhenNotActive()
    {
        $handler = $this->getErrorHandlerMock(true, true, false);

        $handler
            ->expects($this->never())
            ->method('onException')
        ;

        @strlen(); // simulate error for error_get_last() to work

        $handler->onShutdown();
    }

    public function testEvents()
    {
        $handler = $this->getErrorHandlerMock();
        $handler->setDebug(true);

        $that = $this;

        $listenerAssertionException = null;
        $callCounters = array(
            'error' => 0,
            'suppressed_error' => 0,
            'fatal_error' => 0,
        );

        // helper: assert listener call counts
        $assertCallCounts = function (
            $expectedErrorListenerCallCount,
            $expectedSuppressedErrorListenerCallCount,
            $expectedFatalErrorListenerCallCount
        ) use (
            $that,
            &$callCounters
        ) {
            $that->assertSame($expectedErrorListenerCallCount, $callCounters['error'], 'expected error listener to be called n times');
            $that->assertSame($expectedSuppressedErrorListenerCallCount, $callCounters['suppressed_error'], 'expected suppressed error listener to be called n times');
            $that->assertSame($expectedFatalErrorListenerCallCount, $callCounters['fatal_error'], 'expected fatal error listener to be called n times');
        };

        // helper: rethrow listener assertion exception
        $rethrowListenerAssertionException = function () use (&$listenerAssertionException) {
            if (null !== $listenerAssertionException) {
                throw $listenerAssertionException;
            }
        };

        // error listener
        $errorListener = function ($exception, $debug, $suppressed) use (
            $that,
            &$callCounters,
            &$listenerAssertionException
        ) {
            if (!$suppressed) {
                try {
                    $that->assertTrue($debug);
                    $that->assertInstanceOf('ErrorException', $exception);
                } catch (\PHPUnit_Exception $e) {
                    $listenerAssertionException = $e;
                }

                ++$callCounters['error'];
            }
        };

        // suppressed error listener
        $suppressedErrorListener = function ($exception, $debug, $suppressed) use (
            $that,
            &$callCounters,
            &$listenerAssertionException
        ) {
            if ($suppressed) {
                try {
                    $that->assertTrue($debug);
                    $that->assertInstanceOf('ErrorException', $exception);
                } catch (\PHPUnit_Exception $e) {
                    $listenerAssertionException = $e;
                }

                ++$callCounters['suppressed_error'];
            }
        };

        // fatal error listener
        $fatalErrorListener = function (
            $exception,
            $debug,
            $fatalHandler
        ) use (
            $that,
            &$callCounters,
            &$listenerAssertionException
        ) {
            try {
                $that->assertTrue($debug);
                $that->assertInstanceOf('Exception', $exception);
                $that->assertInstanceOf(__NAMESPACE__ . '\FatalErrorHandlerInterface', $fatalHandler);
            } catch (\PHPUnit_Exception $e) {
                $listenerAssertionException = $e;
            }

            ++$callCounters['fatal_error'];
        };

        // attach listeners
        $handler
            ->on('error', $errorListener)
            ->on('error', $suppressedErrorListener)
            ->on('fatal', $fatalErrorListener)
        ;

        // test
        $rethrowListenerAssertionException();
        $assertCallCounts(0, 0, 0);

        try {
            $handler->onError(E_USER_ERROR, 'Test');
        } catch (\ErrorException $e) {
        }

        $rethrowListenerAssertionException();
        $assertCallCounts(1, 0, 0);

        try {
            @$handler->onError(E_USER_ERROR, 'Test suppressed');
        } catch (\ErrorException $e) {
        }

        $rethrowListenerAssertionException();
        $assertCallCounts(1, 1, 0);

        try {
            $handler->onException(new \Exception('Test fatal'));
        } catch (\ErrorException $e) {
        }

        $rethrowListenerAssertionException();
        $assertCallCounts(1, 1, 1);
    }

    public function testEventSuppressRuntimeException()
    {
        $handler = $this->getErrorHandlerMock();

        $handler->on('error', function ($exception, $debug, &$suppressed) {
            $suppressed = true;
        });

        // no exception should be thrown
        $handler->onError(E_USER_ERROR, 'Test');
    }

    /**
     * @expectedException        ErrorException
     * @expectedExceptionMessage Test suppressed
     */
    public function testEventForceRuntimeException()
    {
        $handler = $this->getErrorHandlerMock();

        $handler->on('error', function ($exception, $debug, &$suppressed) {
            $suppressed = false;
        });

        // an exception should be thrown even if suppressed
        @$handler->onError(E_USER_ERROR, 'Test suppressed');
    }

    public function testEventReplaceFatalHandler()
    {
        $handler = $this->getErrorHandlerMock(false, false);
        $customFatalHandler = $this->getFatalErrorHandlerMock();

        $customFatalHandler
            ->expects($this->once())
            ->method('handle')
        ;

        $handler->on('fatal', function ($exception, $debug, &$fatalHandler) use ($customFatalHandler) {
            $fatalHandler = $customFatalHandler;
        });

        $handler->onException(new \Exception('Test fatal'));
    }

    public function testEventDisableFatalHandler()
    {
        $handler = $this->getErrorHandlerMock(false, false);
        $customFatalHandler = $this->getFatalErrorHandlerMock();

        $customFatalHandler
            ->expects($this->never())
            ->method('handle')
        ;

        $handler->setFatalHandler($customFatalHandler);

        $handler->on('fatal', function ($exception, $debug, &$fatalHandler) {
            $fatalHandler = null;
        });

        $handler->onException(new \Exception('Test fatal'));
    }

    public function testEventExceptionChainingDuringRuntime()
    {
        $handler = $this->getErrorHandlerMock();

        $eventException = new \Exception('Event exception');

        $handler->on('error', function () use ($eventException) {
            throw $eventException;
        });

        $runtimeException = null;
        try {
            $handler->onError(E_USER_ERROR, 'Test runtime');
        } catch (\Exception $runtimeException) {
        }

        $this->assertInstanceOf('Exception', $runtimeException, 'expected onError() to throw an exception');
        $this->assertSame($eventException, $runtimeException, 'expected event exception to replace the original error');
        $this->assertInstanceOf('ErrorException', $runtimeException->getPrevious(), 'expected the original error to be chained to the event exception');
        $this->assertSame('Test runtime', $runtimeException->getPrevious()->getMessage(), 'expected the correct exception to be chained');
    }

    public function testEventExceptionChainingDuringFatal()
    {
        $handler = $this->getErrorHandlerMock();

        $eventException = new \Exception('Event exception');
        $fatalException = new \Exception('Test fatal');

        $handler
            ->expects($this->once())
            ->method('handleFatal')
            ->with(
                $this->anything(),
                $this->logicalAnd(
                    $this->identicalTo($eventException),
                    $this->callback(function ($eventException) use ($fatalException) {
                        return $eventException->getPrevious() === $fatalException;
                    })
                )
            )
        ;

        $handler->on('fatal', function () use ($eventException) {
            throw $eventException;
        });

        $handler->onException($fatalException);
    }

    public function testEmergencyEvent()
    {
        $handler = $this->getErrorHandlerMock();

        $that = $this;

        $emergencyException = new \Exception('Emergency exception');
        $fatalException = new \Exception('Test fatal');

        $emergencyHandlerCalled = false;

        $handler->on('emerg', function ($exception) use (
            $that,
            $emergencyException,
            $fatalException,
            &$emergencyHandlerCalled
        ) {
            $emergencyHandlerCalled = true;

            $that->assertSame($emergencyException, $exception, 'expected the emergency exception to be passed to the emergency handler');
            $that->assertSame($fatalException, $exception->getPrevious(), 'expected the original error to be chained to the emergency exception');
        });

        $handler
            ->expects($this->once())
            ->method('handleFatal')
            ->willReturnCallback(function () use ($emergencyException) {
                throw $emergencyException;
            })
        ;

        $handler->onException($fatalException);

        $this->assertTrue($emergencyHandlerCalled, 'expected emergency handler to be called');
    }

    /**
     * @param bool $disableExceptionHandling make onException() do nothnig
     * @param bool $disableOnFatal           make handleFatal() do nothing
     * @param bool $alwaysActive             make isActive() always return true
     * @return \PHPUnit_Framework_MockObject_MockObject|ErrorHandler
     */
    private function getErrorHandlerMock($disableExceptionHandling = false, $disableOnFatal = true, $alwaysActive = true)
    {
        $methods = array();

        if ($alwaysActive) {
            $methods[] = 'isActive';
        }
        if ($disableExceptionHandling) {
            $methods[] = 'onException';
        }
        if ($disableOnFatal) {
            $methods[] = 'handleFatal';
        }

        $handler = $this->getMock(__NAMESPACE__ . '\ErrorHandler', $methods);

        $handler->setCleanBuffers(false);

        if ($alwaysActive) {
            $handler
                ->expects($this->any())
                ->method('isActive')
                ->willReturn(true)
            ;
        }

        return $handler;
    }

    /**
     * @return FatalErrorHandlerInterface
     */
    private function getFatalErrorHandlerMock()
    {
        return $this->getMock(__NAMESPACE__ . '\FatalErrorHandlerInterface');
    }
}
