<?php

namespace Kuria\Error;

use Kuria\Debug\Error;
use Kuria\Debug\Output;
use Kuria\Error\Screen\CliErrorScreen;
use Kuria\Error\Screen\WebErrorScreen;
use Kuria\Event\EventEmitter;

/**
 * Error handler
 *
 * @emits error(\ErrorException $exception, bool $debug, bool &suppressed)
 * @emits exception(\Throwable $exception, bool $debug, int $errorType)
 * @emits failure(\Throwable $exception, bool $debug, int $errorType)
 *
 * @author ShiraNai7 <shira.cz>
 */
class ErrorHandler extends EventEmitter
{
    /** Error type - fatal error */
    const FATAL_ERROR = 0;
    /** Error type - uncaught exception */
    const UNCAUGHT_EXCEPTION = 1;
    /** Error type - out of memory */
    const OUT_OF_MEMORY = 2;

    /** @var bool */
    protected $debug = false;
    /** @var bool */
    protected $printUnhandledExceptionInDebug = true;
    /** @var string|null */
    protected $workingDirectory;
    /** @var ExceptionHandlerInterface */
    protected $exceptionHandler;
    /** @var bool */
    protected $cleanBuffers = true;
    /** @var \Throwable|\Exception|null */
    protected $currentErrorException;
    /** @var array|null */
    protected $lastError;
    /** @var bool */
    protected $registered = false;
    /** @var string|null */
    protected $previousDisplayErrorsSetting;
    /** @var bool */
    protected $shutdownHandlerRegistered = false;
    /** @var string|null */
    protected $reservedMemory;

    /**
     * @param ExceptionHandlerInterface|null $exceptionHandler exception handle instance or null to use the default
     * @param int                            $reserveMemory   reserve this many bytes to be able to handle out-of-memory errors (0 = disable)
     */
    public function __construct(ExceptionHandlerInterface $exceptionHandler = null, $reserveMemory = 10240)
    {
        $this->exceptionHandler = $exceptionHandler ?: $this->getDefaultExceptionHandler();
        $this->reservedMemory = $reserveMemory > 0 ? str_repeat('.', $reserveMemory) : null;
        $this->workingDirectory = getcwd();
    }

    /**
     * Register the error handler
     */
    public function register()
    {
        if (!$this->registered) {
            set_error_handler(array($this, 'onError'));
            set_exception_handler(array($this, 'onUncaughtException'));

            $this->previousDisplayErrorsSetting = ini_get('display_errors');
            ini_set('display_errors', '0');

            $this->registered = true;

            if (!$this->shutdownHandlerRegistered) {
                register_shutdown_function(array($this, 'onShutdown'));
                $this->shutdownHandlerRegistered = true;
            }

            // store last known error
            // this prevents a bogus fatal error on shutdown if an error has already happened
            $this->lastError = error_get_last();
        }
    }

    /**
     * Unregister the error handler
     */
    public function unregister()
    {
        if ($this->registered) {
            set_error_handler(null);
            set_exception_handler(null);
            ini_set('display_errors', $this->previousDisplayErrorsSetting);

            $this->previousDisplayErrorsSetting = null;
            $this->registered = false;
        }
    }

    /**
     * @return bool
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * @param bool $debug
     * @return static
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * @param bool $printUnhandledExceptionInDebug
     * @return static
     */
    public function setPrintUnhandledExceptionInDebug($printUnhandledExceptionInDebug)
    {
        $this->printUnhandledExceptionInDebug = $printUnhandledExceptionInDebug;

        return $this;
    }

    /**
     * Set working directory to use when handling fatal errors
     *
     * This is useful because some SAPIs may change the working directory inside shutdown functions.
     *
     * Set to NULL to keep the working directory unchanged (not recommended).
     *
     * @param string|null $workingDirectory
     * @return static
     */
    public function setWorkingDirectory($workingDirectory)
    {
        $this->workingDirectory = $workingDirectory;

        return $this;
    }

    /**
     * Get the exception handler
     *
     * @return ExceptionHandlerInterface
     */
    public function getExceptionHandler()
    {
        return $this->exceptionHandler;
    }

    /**
     * @param ExceptionHandlerInterface $exceptionHandler
     * @return static
     */
    public function setExceptionHandler(ExceptionHandlerInterface $exceptionHandler)
    {
        $this->exceptionHandler = $exceptionHandler;

        return $this;
    }

    /**
     * Set whether output buffers should be cleaned before the exception handler is called
     *
     * @param bool $cleanBuffers
     * @return static
     */
    public function setCleanBuffers($cleanBuffers)
    {
        $this->cleanBuffers = $cleanBuffers;

        return $this;
    }

    /**
     * Handle a PHP error
     *
     * @param int         $code    error code
     * @param string      $message message
     * @param string|null $file    file name
     * @param int|null    $line    line number
     * @return bool
     */
    public function onError($code, $message, $file = null, $line = null)
    {
        $this->lastError = error_get_last();
        $this->currentErrorException = new \ErrorException($message, 0, $code, $file, $line);
        $suppressed = 0 === ($code & error_reporting());

        if ($this->hasListeners('error')) {
            // make sure autoloading is active before emitting an event
            // (autoloading is inactive in some PHP versions during "compile-time errors")
            // (the bug appears to have been fixed in PHP 5.4.21+, 5.5.5+ and 5.6.0+)
            // https://bugs.php.net/42098
            if (
                PHP_MAJOR_VERSION > 5 // PHP 7+
                || PHP_MINOR_VERSION >= 6 // PHP 5.6+
                || PHP_MINOR_VERSION === 5 && PHP_RELEASE_VERSION >= 5 // PHP 5.5.5+
                || PHP_MINOR_VERSION === 4 && PHP_RELEASE_VERSION >= 21 // PHP 5.4.21+
                || $this->isAutoloadingActive()
            ) {
                $errorListenerException = null;
                try {
                    $this->emitArray('error', array($this->currentErrorException, $this->debug, &$suppressed));
                } catch (\Exception $errorListenerException) {
                } catch (\Throwable $errorListenerException) {
                }
                if (null !== $errorListenerException) {
                    $this->currentErrorException = new \RuntimeException(
                        'Additional exception was thrown from an [error] event listener. See previous exceptions.',
                        0,
                        Error::joinExceptionChains($this->currentErrorException, $errorListenerException)
                    );
                    $suppressed = false;
                }
            }
        }

        if (!$suppressed) {
            $error = $this->currentErrorException;
            $this->currentErrorException = null;

            throw $error;
        }

        // suppressed error
        return true;
    }

    /**
     * Handle an uncaught exception
     *
     * @param \Throwable|\Exception $exception
     */
    public function onUncaughtException($exception)
    {
        $this->handleException($exception, static::UNCAUGHT_EXCEPTION);
    }

    /**
     * Check for a fatal error on shutdown
     *
     * @internal
     */
    public function onShutdown()
    {
        // free the reserved memory
        $this->reservedMemory = null;

        if (
            $this->isActive()
            && null !== ($error = error_get_last())
            && $error !== $this->lastError
        ) {
            $this->lastError = null;

            // fix working directory
            if (null !== $this->workingDirectory) {
                chdir($this->workingDirectory);
            }

            // determine error type
            if ($this->isOutOfMemoryError($error)) {
                $errorType = static::OUT_OF_MEMORY;
                gc_collect_cycles();
            } else {
                $errorType = static::FATAL_ERROR;
            }

            // handle
            $this->handleException(
                new \ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line'],
                    $this->currentErrorException // use current error exception if a fatal error happens during onError()
                ),
                $errorType
            );
        }
    }

    /**
     * Get the default exception handler
     *
     * @return ExceptionHandlerInterface
     */
    protected function getDefaultExceptionHandler()
    {
        return $this->isCli() ? new CliErrorScreen() : new WebErrorScreen();
    }

    /**
     * Handle an exception
     *
     * @param \Throwable|\Exception $exception
     * @param int                   $errorType
     */
    protected function handleException($exception, $errorType)
    {
        $exceptionHandlerException = null;
        try {
            // handle the exception
            $exceptionListenerException = null;
            try {
                $this->emit('exception', $exception, $this->debug, $errorType);
            } catch (\Exception $exceptionListenerException) {
            } catch (\Throwable $exceptionListenerException) {
            }

            if (null !== $exceptionListenerException) {
                $exception = new \RuntimeException(
                    'Additional exception was thrown from an [exception] event listener. See previous exceptions.',
                    0,
                    Error::joinExceptionChains($exception, $exceptionListenerException)
                );
            }

            $this->callExceptionHandler($exception, $errorType);

            return;
        } catch (\Exception $exceptionHandlerException) {
        } catch (\Throwable $exceptionHandlerException) {
        }

        if (null !== $exceptionHandlerException) {
            $exception = new \RuntimeException(
                sprintf('Additonal exception was thrown while trying to call %s. See previous exceptions.', get_class($this->exceptionHandler)),
                0,
                Error::joinExceptionChains($exception, $exceptionHandlerException)
            );

            if ($this->hasListeners('failure')) {
                $this->emit('failure', $exception, $this->debug, $errorType);

                return;
            }
        }

        // unhandled exception
        if ($this->debug && $this->printUnhandledExceptionInDebug) {
            echo Error::renderException($exception, true, true);
        }
    }

    /**
     * Call the registered exception handler
     *
     * @param \Throwable|\Exception $exception
     * @param int                   $errorType
     */
    protected function callExceptionHandler($exception, $errorType)
    {
        // replace headers
        if (!$this->isCli()) {
            Output::replaceHeaders(array('HTTP/1.1 500 Internal Server Error'));
        }

        // clean output buffers
        $outputBuffer = null;
        if ($this->cleanBuffers) {
            // capture output buffers unless out of memory
            if (static::OUT_OF_MEMORY !== $errorType) {
                $outputBuffer = Output::cleanBuffers(null, true, true);
            } else {
                Output::cleanBuffers(null, false, true);
            }
        }

        // handle
        $this->exceptionHandler->handle($exception, $errorType, $this->debug, $outputBuffer);
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
     * @param array $error
     * @return bool
     */
    protected function isOutOfMemoryError(array $error)
    {
        return 0 === strncasecmp($error['message'], 'Allowed memory size of ', 23)
            || 0 === strncasecmp($error['message'], 'Out of memory', 13);
    }

    /**
     * See if this is the current error handler
     *
     * @return bool
     */
    protected function isActive()
    {
        if (null !== $this->currentErrorException) {
            return true;
        }

        // ugly, but there is no get_error_handler()..
        $currentErrorHandler = set_error_handler(function () {});
        restore_error_handler();

        return is_array($currentErrorHandler) && $currentErrorHandler[0] === $this;
    }

    /**
     * Try to detect whether autoloading is currently active
     *
     * @return bool
     */
    protected function isAutoloadingActive()
    {
        $testClass = 'Kuria\Error\__Internal__\Nonexistent_Class';

        $autoloadingActive = false;
        $autoloadChecker = function ($class) use (&$autoloadingActive, $testClass) {
            if ($class === $testClass) {
                $autoloadingActive = true;
            }
        };

        if (spl_autoload_register($autoloadChecker, false, true)) {
            class_exists($testClass);
        }
        spl_autoload_unregister($autoloadChecker);

        return $autoloadingActive;
    }
}
