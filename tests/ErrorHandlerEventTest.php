<?php

namespace Kuria\Error;

class ErrorHandlerEventTest extends \PHPUnit_Framework_TestCase
{
    public function testRuntime()
    {
        $exception = new \Exception('Surprise exception');
        $event = new ErrorHandlerEvent(true, false, false, $exception, null);

        $this->assertTrue($event->getDebug());
        $this->assertSame($exception, $event->getException());
        $this->assertFalse($event->isFatal());
        $this->assertTrue($event->isRuntime());
        $this->assertFalse($event->isSuppressed());
        $this->assertNull($event->getRuntimeExceptionDecision());
        $this->assertFalse($event->isRendererEnabled());

        $event->forceRuntimeException();

        $this->assertTrue($event->getRuntimeExceptionDecision());
        $this->assertTrue($event->isForcingRuntimeException());
        $this->assertFalse($event->isSuppressingRuntimeException());

        $event->suppressRuntimeException();

        $this->assertFalse($event->getRuntimeExceptionDecision());
        $this->assertFalse($event->isForcingRuntimeException());
        $this->assertTrue($event->isSuppressingRuntimeException());

        $event->restoreRuntimeException();

        $this->assertNull($event->getRuntimeExceptionDecision());
        $this->assertFalse($event->isForcingRuntimeException());
        $this->assertFalse($event->isSuppressingRuntimeException());
    }

    public function testFatal()
    {
        $exception = new \Exception('It is all your fault');
        $renderer = $this->getMock(__NAMESPACE__ . '\ExceptionRendererInterface');
        $event = new ErrorHandlerEvent(false, true, true, $exception, $renderer);

        $this->assertFalse($event->getDebug());
        $this->assertSame($exception, $event->getException());
        $this->assertTrue($event->isFatal());
        $this->assertFalse($event->isRuntime());
        $this->assertTrue($event->isSuppressed());
        $this->assertNull($event->getRuntimeExceptionDecision());
        $this->assertTrue($event->isRendererEnabled());
        $this->assertSame($renderer, $event->getRenderer());

        $event->disableRenderer();

        $this->assertFalse($event->isRendererEnabled());

        $event->enableRenderer();

        $this->assertTrue($event->isRendererEnabled());
    }

    /**
     * @expectedException LogicException
     */
    public function testGetRendererOnRuntimeErrorThrowsException()
    {
        $exception = new \Exception('Surprise exception');
        $event = new ErrorHandlerEvent(true, false, false, $exception, null);

        $event->getRenderer();
    }

    /**
     * @expectedException LogicException
     */
    public function testReplaceRendererOnRuntimeErrorThrowsException()
    {
        $exception = new \Exception('Surprise exception');
        $event = new ErrorHandlerEvent(true, false, false, $exception, null);

        $event->replaceRenderer($this->getMock(__NAMESPACE__ . '\ExceptionRendererInterface'));
    }

    /**
     * @expectedException LogicException
     */
    public function testDisableRendererOnRuntimeErrorThrowsException()
    {
        $exception = new \Exception('Surprise exception');
        $event = new ErrorHandlerEvent(true, false, false, $exception, null);

        $event->disableRenderer();
    }

    /**
     * @expectedException LogicException
     */
    public function testEnableRendererOnRuntimeErrorThrowsException()
    {
        $exception = new \Exception('Surprise exception');
        $event = new ErrorHandlerEvent(true, false, false, $exception, null);

        $event->enableRenderer();
    }

    /**
     * @expectedException LogicException
     */
    public function testForceRuntimeExceptionOnFatalErrorThrowsException()
    {
        $exception = new \Exception('It is all your fault');
        $renderer = $this->getMock(__NAMESPACE__ . '\ExceptionRendererInterface');

        $event = new ErrorHandlerEvent(true, true, false, $exception, $renderer);

        $event->forceRuntimeException();
    }

    /**
     * @expectedException LogicException
     */
    public function testSuppressRuntimeExceptionOnFatalErrorThrowsException()
    {
        $exception = new \Exception('It is all your fault');
        $renderer = $this->getMock(__NAMESPACE__ . '\ExceptionRendererInterface');

        $event = new ErrorHandlerEvent(true, true, false, $exception, $renderer);

        $event->suppressRuntimeException();
    }

    /**
     * @expectedException LogicException
     */
    public function testRestoreRuntimeExceptionOnFatalErrorThrowsException()
    {
        $exception = new \Exception('It is all your fault');
        $renderer = $this->getMock(__NAMESPACE__ . '\ExceptionRendererInterface');

        $event = new ErrorHandlerEvent(true, true, false, $exception, $renderer);

        $event->restoreRuntimeException();
    }
}
