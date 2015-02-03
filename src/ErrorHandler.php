<?php

namespace Kuria\Error;

use Kuria\Event\ExternalObservable;
use Kuria\Event\Observable;

/**
 * Error handler
 *
 * @author ShiraNai7 <shira.cz>
 */
class ErrorHandler extends ExternalObservable
{
    /** @var bool */
    protected $debug;
    /** @var string|null */
    protected $cwd;
    /** @var callable|null */
    protected $emergencyHandler;

    /** @var bool */
    protected $handlingError = false;
    /** @var bool */
    protected $handlingException = false;
    /** @var \ErrorException|null */
    protected $currentException;
    /** @var array|null */
    protected $lastError;

    /** @var bool */
    protected $registered = false;

    /**
     * @param bool        $debug debug mode 1/0
     * @param string|null $cwd   current working directory (null = getcwd())
     */
    public function __construct($debug = false, $cwd = null)
    {
        $this->debug = $debug;
        $this->cwd = $cwd ?: getcwd();

        // make sure the certain classes are loaded
        // (autoloading may be unavailable during compile-time errors)
        if (!class_exists('Kuria\Error\DebugUtils')) {
            require __DIR__ . '/DebugUtils.php';
        }
        if (!class_exists('Kuria\Error\ErrorHandlerEvent')) {
            require __DIR__ . '/ErrorHandlerEvent.php';
        }
    }

    protected function handleNullObservable()
    {
        $this->observable = new Observable();
    }

    /**
     * Register the error handler
     */
    public function register()
    {
        if (!$this->registered) {
            set_error_handler(array($this, 'onError'));
            set_exception_handler(array($this, 'onException'));
            register_shutdown_function(array($this, 'onShutdown'));

            $this->registered = true;

            // store last known error
            // this prevents a "fake" fatal error on shutdown
            $this->lastError = error_get_last();
        }
    }

    /**
     * Unregister the error handler
     */
    public function unregister()
    {
        if ($this->registered) {
            restore_error_handler();
            restore_exception_handler();

            $this->registered = false;
        }
    }

    /**
     * Set debug mode
     *
     * @param bool $debug
     * @return static
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * Set CWD
     *
     * The current working directory is stored for later use.
     * Some web servers may change the CWD inside shutdown functions.
     *
     * @param string $cwd
     * @return static
     */
    public function setCwd($cwd)
    {
        $this->cwd = $cwd;

        return $this;
    }

    /**
     * Set the emergency handler
     *
     * @param callable $emergencyHandler callback(Exception): void
     * @return static
     */
    public function setEmergencyHandler($emergencyHandler)
    {
        $this->emergencyHandler = $emergencyHandler;

        return $this;
    }

    /**
     * Handle PHP error
     *
     * @param int         $code    error code
     * @param string      $message message
     * @param string|null $file    file name
     * @param int|null    $line    line number
     * @throws \ErrorException unless suppressed
     * @return bool
     */
    public function onError($code, $message, $file = null, $line = null)
    {
        $this->handlingError = true;
        $this->lastError = error_get_last();
        $this->currentException = new \ErrorException($message, 0, $code, $file, $line);
        
        $supressed = 0 === ($code & error_reporting());

        // handle the error
        if (null !== $this->observable) {
            // notify observers
            try {
                $event = $this->observable->notifyObservers(
                    new ErrorHandlerEvent($this->debug, false, $supressed, $this->currentException)
                );

                if (null !== ($eventDecision = $event->getRuntimeExceptionDecision())) {
                    $supressed = !$eventDecision;
                }
            } catch (\Exception $eventException) {
                $this->currentException = DebugUtils::joinExceptionChains($this->currentException, $eventException);
                $supressed = false;
            }
        }

        $this->handlingError = false;

        // throw or suppress
        if (!$supressed) {
            throw $this->currentException;
        } else {
            return true;
        }
    }

    /**
     * Check for a fatal error on shutdown
     */
    public function onShutdown()
    {
        if (
            $this->isActive()
            && null !== ($error = error_get_last())
            && $error !== $this->lastError
        ) {
            $previous = null;

            // check current state
            if ($this->handlingError && null !== $this->currentException) {
                // the fatal error has happened during onError()
                // connect the original exception
                $previous = $this->currentException;
            }

            // handle the error
            $this->onException(new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line'],
                $previous
            ));
        }
    }

    /**
     * Handle an uncaught exception
     *
     * @param \Exception $exception
     */
    public function onException(\Exception $exception)
    {
        if ($this->handlingException) {
            return;
        }
        $this->handlingException = true;

        try {
            // fix working directory
            if (null !== $this->cwd) {
                chdir($this->cwd);
            }

            // create the renderer
            $renderer = $this->createRenderer();

            // handle exception
            try {
                if (null !== $this->observable) {
                    // notify observers
                    $suppressed = $exception instanceof \ErrorException
                        && 0 === ($exception->getSeverity() & error_reporting())
                    ;

                    $event = $this->observable->notifyObservers(
                        new ErrorHandlerEvent($this->debug, true, $suppressed, $exception, $renderer)
                    );

                    if ($event->isRendererEnabled()) {
                        $renderer = $event->getRenderer();
                    } else {
                        $renderer = null;
                    }
                }
            } catch (\Exception $observerException) {
                // something went wrong while notifying the observers
                $exception = DebugUtils::joinExceptionChains($exception, $observerException);
            }

            // render the error
            if (null !== $renderer) {
                $this->renderException($renderer, $exception);
            }
        } catch (\Exception $handlerException) {
            // something went terribly wrong
            $this->handleEmergency(DebugUtils::joinExceptionChains($exception, $handlerException));
        }

        $this->handlingException = false;
    }

    /**
     * Handle emergency
     *
     * @param \Exception $exception
     */
    protected function handleEmergency(\Exception $exception)
    {
        if (null !== $this->emergencyHandler) {
            call_user_func($this->emergencyHandler, $exception);
        }
    }

    /**
     * Render exception
     *
     * @param ExceptionRendererInterface $renderer
     * @param \Exception                 $exception
     */
    protected function renderException(ExceptionRendererInterface $renderer, \Exception $exception)
    {
        // prepare
        $isCli = $this->isCli();
        if (!$isCli) {
            DebugUtils::replaceHeaders(array('HTTP/1.1 500 Internal Server Error'));
        }
        $outputBuffer = DebugUtils::cleanBuffers(true);

        // render
        $renderer->render($this->debug, $exception, $outputBuffer);
    }

    /**
     * Create instance of error renderer
     *
     * @return ExceptionRendererInterface
     */
    protected function createRenderer()
    {
        $isCli = $this->isCli();

        // load classes manually if autoloading is unavailable
        // (this might happen during compile-time errors)
        if (!interface_exists('Kuria\Error\ExceptionRendererInterface')) {
            require __DIR__ . '/ExceptionRendererInterface.php';
        }
        if ($isCli) {
            if (!class_exists('Kuria\Error\CliExceptionRenderer')) {
                require __DIR__ . '/CliExceptionRenderer.php';
            }
        } elseif (!class_exists('Kuria\Error\WebExceptionRenderer')) {
            require __DIR__ . '/WebExceptionRenderer.php';
        }

        return $isCli
            ? new CliExceptionRenderer()
            : new WebExceptionRenderer()
        ;
    }

    /**
     * Detect CLI environment
     *
     * @return bool
     */
    protected function isCli()
    {
        return 'cli' === PHP_SAPI;
    }

    /**
     * See if this is the current error handler
     *
     * @return bool
     */
    protected function isActive()
    {
        if ($this->handlingError) {
            return true;
        } else {
            $currentErrorHandler = set_error_handler(function () {}, 0);
            restore_error_handler();

            return is_array($currentErrorHandler) && $currentErrorHandler[0] === $this;
        }
    }
}
