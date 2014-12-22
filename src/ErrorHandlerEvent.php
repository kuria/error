<?php

namespace Kuria\Error;

use Kuria\Event\Event;

/**
 * Error handler event
 *
 * @author ShiraNai7 <shira.cz>
 */
class ErrorHandlerEvent extends Event
{
    /** Event name - runtime error */
    const RUNTIME = 'kuria.error.runtime';
    /** Event name - suppressed runtime error */
    const RUNTIME_SUPPRESSED = 'kuria.error.runtime_suppressed';
    /** Event name - fatal error */
    const FATAL = 'kuria.error.fatal';

    /** @var bool */
    protected $debug;
    /** @var bool */
    protected $fatal;
    /** @var bool */
    protected $suppressed;
    /** @var \Exception */
    protected $exception;
    /** @var ExceptionRendererInterface|null */
    protected $renderer;
    /** @var bool */
    protected $rendererEnabled;
    /** @var bool|null */
    protected $runtimeExceptionDecision = null;

    /**
     * @param bool                            $debug
     * @param bool                            $fatal
     * @param bool                            $suppressed
     * @param \Exception                      $exception
     * @param ExceptionRendererInterface|null $renderer
     */
    public function __construct(
        $debug,
        $fatal,
        $suppressed,
        \Exception $exception,
        ExceptionRendererInterface $renderer = null
    ) {
        $this->debug = $debug;
        $this->fatal = $fatal;
        $this->suppressed = $suppressed;
        $this->exception = $exception;
        $this->renderer = $renderer;
        $this->rendererEnabled = $fatal;

        if ($fatal) {
            $this->name = self::FATAL;
        } elseif ($suppressed) {
            $this->name = self::RUNTIME_SUPPRESSED;
        } else {
            $this->name = self::RUNTIME;
        }
    }

    /**
     * Get debug state
     *
     * @return bool
     */
    public function getDebug()
    {
        return $this->debug;
    }
    
    /**
     * Get the exception
     *
     * @return \Exception
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * See if the error is fatal
     *
     * @return bool
     */
    public function isFatal()
    {
        return $this->fatal;
    }

    /**
     * See if the error is runtime
     *
     * @return bool
     */
    public function isRuntime()
    {
        return !$this->fatal;
    }

    /**
     * See if the error is suppressed (by current error_reporting)
     *
     * @return bool
     */
    public function isSuppressed()
    {
        return $this->suppressed;
    }

    /**
     * Get runtime exception decision
     *
     * true = force the exception
     * false = suppress the exception
     * null = do not change the behavior
     *
     * @return bool|null
     */
    public function getRuntimeExceptionDecision()
    {
        return $this->runtimeExceptionDecision;
    }

    /**
     * Force runtime exception to be thrown, even if the error is suppressed
     *
     * @throws \LogicException if the error is fatal
     */
    public function forceRuntimeException()
    {
        if ($this->fatal) {
            throw new \LogicException('Forcing runtime exception for a fatal error makes no sense');
        }

        $this->runtimeExceptionDecision = true;
    }

    /**
     * Suppress runtime exception, even if it would be thrown
     *
     * @throws \LogicException if the error is fatal
     */
    public function suppressRuntimeException()
    {
        if ($this->fatal) {
            throw new \LogicException('Suppressing runtime exception for a fatal error makes no sense');
        }

        $this->runtimeExceptionDecision = false;
    }

    /**
     * Restore runtime exception state
     *
     * @throws \LogicException if the error is fatal
     */
    public function restoreRuntimeException()
    {
        if ($this->fatal) {
            throw new \LogicException('Restoring runtime exception for a fatal error makes no sense');
        }

        $this->runtimeExceptionDecision = null;
    }

    /**
     * See if the runtime exception is being forced
     *
     * @return bool
     */
    public function isForcingRuntimeException()
    {
        return true === $this->runtimeExceptionDecision;
    }

    /**
     * See if the runtime exception is being suppressed
     *
     * @return bool
     */
    public function isSuppressingRuntimeException()
    {
        return false === $this->runtimeExceptionDecision;
    }

    /**
     * Get the exception renderer
     *
     * @throws \LogicException if the error is not fatal
     * @return ExceptionRendererInterface
     */
    public function getRenderer()
    {
        if (!$this->fatal) {
            throw new \LogicException('Renderer is not available for runtime errors');
        }

        return $this->renderer;
    }

    /**
     * Replace the exception renderer
     *
     * @param ExceptionRendererInterface $newRenderer
     * @throws \LogicException if the error is not fatal
     */
    public function replaceRenderer(ExceptionRendererInterface $newRenderer)
    {
        if (!$this->fatal) {
            throw new \LogicException('Renderer cannot be replaced for runtime errors');
        }

        $this->renderer = $newRenderer;
    }

    /**
     * Disable the exception renderer
     *
     * @throws \LogicException if the error is not fatal
     */
    public function disableRenderer()
    {
        if (!$this->fatal) {
            throw new \LogicException('Renderer cannot be disabled for runtime errors');
        }

        $this->rendererEnabled = false;
    }

    /**
     * Enable the exception renderer
     *
     * @throws \LogicException if the error is not fatal
     */
    public function enableRenderer()
    {
        if (!$this->fatal) {
            throw new \LogicException('Renderer cannot be enabled for runtime errors');
        }

        $this->rendererEnabled = true;
    }

    /**
     * See if the renderer is enabled
     *
     * @return bool
     */
    public function isRendererEnabled()
    {
        return $this->rendererEnabled;
    }
}
