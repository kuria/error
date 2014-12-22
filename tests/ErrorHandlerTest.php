<?php

namespace Kuria\Error;

use Kuria\Event\EventEmitter;
use Kuria\Event\EventEmitterInterface;

class ErrorHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testConfig()
    {
        $handler = $this->createErrorHandlerMock();

        $handler->setDebug(true);
        $handler->setCwd(__DIR__);
    }

    /**
     * @expectedException ErrorException
     * @expectedExceptionMessage Something went wrong
     */
    public function testOnError()
    {
        $handler = $this->createErrorHandlerMock();

        $handler->onError(E_USER_WARNING, 'Something went wrong', __FILE__, __LINE__);
    }

    public function testOnErrorSuppressed()
    {
        $handler = $this->createErrorHandlerMock();

        @$handler->onError(E_USER_WARNING, 'Something went wrong', __FILE__, __LINE__);
    }

    public function testOnException()
    {
        $handler = $this->createErrorHandlerMock();

        $handler->expects($this->once())
            ->method('renderException')
        ;

        $handler->onException(new \Exception('Boo'));
    }

    public function testOnShutdownWithNoErrors()
    {
        $handler = $this->createErrorHandlerMock();

        $handler->expects($this->never())
            ->method('renderException')
        ;

        $handler->onShutdown();
    }

    public function testOnShutdownWithSuppressedError()
    {
        $handler = $this->createErrorHandlerMock();

        $handler->expects($this->never())
            ->method('renderException')
        ;

        @strlen(); // simulate error for error_get_last() to work

        @$handler->onError(E_USER_ERROR, 'Something went wrong', __FILE__, __LINE__);

        $handler->onShutdown();
    }

    public function testOnShutdownWithHandledError()
    {
        $handler = $this->createErrorHandlerMock();

        $handler->expects($this->never())
            ->method('renderException')
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
        $handler = $this->createErrorHandlerMock();

        $handler->expects($this->once())
            ->method('renderException')
        ;

        @strlen(); // simulate error for error_get_last() to work

        $handler->onShutdown();
    }

    public function testOnShutdownWhenActive()
    {
        $handler = $this->getMock(__NAMESPACE__ . '\ErrorHandler', array(
            'renderException',
            'onException'
        ));

        $handler->expects($this->once())
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
        $handler = $this->getMock(__NAMESPACE__ . '\ErrorHandler', array(
            'renderException',
            'onException'
        ));

        $handler->expects($this->never())
            ->method('onException')
        ;

        @strlen(); // simulate error for error_get_last() to work

        $handler->onShutdown();
    }

    public function testEvents()
    {
        $handler = $this->createErrorHandlerMock();
        $handler->setDebug(true);
        $emitter = $this->addEventEmitterToErrorHandler($handler);

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
        $errorListener = function (
            ErrorHandlerEvent $event,
            EventEmitterInterface $emitter
        ) use (
            $that,
            &$callCounters,
            &$listenerAssertionException
        ) {
            try {
                $that->assertTrue($event->getDebug());
                $that->assertFalse($event->isFatal());
                $that->assertFalse($event->isSuppressed());
                $that->assertInstanceOf('ErrorException', $event->getException());
                $that->assertFalse($event->isRendererEnabled());
            } catch (\Exception $e) {
                $listenerAssertionException = $e;
            }

            ++$callCounters['error'];
        };

        // suppressed error listener
        $suppressedErrorListener = function (
            ErrorHandlerEvent $event,
            EventEmitterInterface $emitter
        ) use (
            $that,
            &$callCounters,
            &$listenerAssertionException
        ) {
            try {
                $that->assertTrue($event->getDebug());
                $that->assertFalse($event->isFatal());
                $that->assertTrue($event->isSuppressed());
                $that->assertInstanceOf('ErrorException', $event->getException());
                $that->assertFalse($event->isRendererEnabled());
            } catch (\Exception $e) {
                $listenerAssertionException = $e;
            }

            ++$callCounters['suppressed_error'];
        };

        // fatal error listener
        $fatalErrorListener = function (
            ErrorHandlerEvent $event,
            EventEmitterInterface $emitter
        ) use (
            $that,
            &$callCounters,
            &$listenerAssertionException
        ) {
            try {
                $that->assertTrue($event->getDebug());
                $that->assertTrue($event->isFatal());
                $that->assertFalse($event->isSuppressed());
                $that->assertInstanceOf('Exception', $event->getException());
                $that->assertInstanceOf(__NAMESPACE__ . '\ExceptionRendererInterface', $event->getRenderer());
            } catch (\Exception $e) {
                $listenerAssertionException = $e;
            }

            ++$callCounters['fatal_error'];
        };

        // attach listeners
        $emitter
            ->addListener(ErrorHandlerEvent::RUNTIME, $errorListener)
            ->addListener(ErrorHandlerEvent::RUNTIME_SUPPRESSED, $suppressedErrorListener)
            ->addListener(ErrorHandlerEvent::FATAL, $fatalErrorListener)
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
        $handler = $this->createErrorHandlerMock();
        $emitter = $this->addEventEmitterToErrorHandler($handler);

        $emitter->addListener(ErrorHandlerEvent::RUNTIME, function (ErrorHandlerEvent $event) {
            $event->suppressRuntimeException();
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
        $handler = $this->createErrorHandlerMock();
        $emitter = $this->addEventEmitterToErrorHandler($handler);

        $emitter->addListener(ErrorHandlerEvent::RUNTIME_SUPPRESSED, function (ErrorHandlerEvent $event) {
            $event->forceRuntimeException();
        });

        // an exception should be thrown even if suppressed
        @$handler->onError(E_USER_ERROR, 'Test suppressed');
    }

    public function testEventReplaceRenderer()
    {
        $handler = $this->createErrorHandlerMock();
        $emitter = $this->addEventEmitterToErrorHandler($handler);

        $rendererMock = $this->getMock(__NAMESPACE__ . '\ExceptionRendererInterface');

        $handler->expects($this->once())
            ->method('renderException')
            ->with($this->identicalTo($rendererMock))
        ;

        $emitter->addListener(ErrorHandlerEvent::FATAL, function (ErrorHandlerEvent $event) use ($rendererMock) {
            $event->replaceRenderer($rendererMock);
        });

        $handler->onException(new \Exception('Test fatal'));
    }

    public function testEventDisableRenderer()
    {
        $handler = $this->createErrorHandlerMock();
        $emitter = $this->addEventEmitterToErrorHandler($handler);

        $handler->expects($this->never())
            ->method('renderException')
        ;

        $emitter->addListener(ErrorHandlerEvent::FATAL, function (ErrorHandlerEvent $event) {
            $event->disableRenderer();
        });

        $handler->onException(new \Exception('Test fatal'));
    }

    public function testEventExceptionChainingDuringRuntime()
    {
        $handler = $this->createErrorHandlerMock();
        $emitter = $this->addEventEmitterToErrorHandler($handler);

        $eventException = new \Exception('Event exception');

        $emitter->addListener(ErrorHandlerEvent::RUNTIME, function (ErrorHandlerEvent $event) use ($eventException) {
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
        $handler = $this->createErrorHandlerMock();
        $emitter = $this->addEventEmitterToErrorHandler($handler);

        $eventException = new \Exception('Event exception');
        $fatalException = new \Exception('Test fatal');

        $handler->expects($this->once())
            ->method('renderException')
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

        $emitter->addListener(ErrorHandlerEvent::FATAL, function (ErrorHandlerEvent $event) use ($eventException) {
            throw $eventException;
        });

        $handler->onException($fatalException);
    }

    public function testEmergencyHandler()
    {
        $handler = $this->createErrorHandlerMock();

        $that = $this;

        $emergencyException = new \Exception('Emergency exception');
        $fatalException = new \Exception('Test fatal');

        $emergencyHandlerCalled = false;

        $handler->setEmergencyHandler(function (\Exception $exception) use (
            $that,
            $emergencyException,
            $fatalException,
            &$emergencyHandlerCalled
        ) {
            $emergencyHandlerCalled = true;

            $that->assertSame($emergencyException, $exception, 'expected the emergency exception to be passed to the emergency handler');
            $that->assertSame($fatalException, $exception->getPrevious(), 'expected the original error to be chained to the emergency exception');
        });

        $handler->expects($this->once())
            ->method('renderException')
            ->willReturnCallback(function () use ($emergencyException) {
                throw $emergencyException;
            })
        ;

        $handler->onException($fatalException);

        $this->assertTrue($emergencyHandlerCalled, 'expected emergency handler to be called');
    }

    /**
     * Create error handler mock
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|ErrorHandler
     */
    private function createErrorHandlerMock()
    {
        $handler = $this->getMock(__NAMESPACE__ . '\ErrorHandler', array(
            'renderException',
            'isActive'
        ));

        $handler->expects($this->any())
            ->method('isActive')
            ->willReturn(true)
        ;

        return $handler;
    }

    /**
     * Add event emitter to the error handler
     *
     * @param ErrorHandler $handler
     * @return EventEmitterInterface
     */
    private function addEventEmitterToErrorHandler(ErrorHandler $handler)
    {
        $emitter = new EventEmitter();

        $handler->setNestedObservable($emitter);

        return $emitter;
    }
}
