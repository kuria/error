<?php

namespace Kuria\Error;

use Kuria\Error\Screen\CliErrorScreen;
use Kuria\Error\Screen\WebErrorScreen;
use Kuria\Error\Util\Debug;
use Kuria\Event\EventEmitter;

/**
 * Error handler
 *
 * @emits error(object $exception, bool $debug, bool &suppressed)
 * @emits fatal(object $exception, bool $debug, FatalErrorHandlerInterface &$handler)
 * @emits emerg(object $exception, bool $debug)
 * 
 * @author ShiraNai7 <shira.cz>
 */
class ErrorHandler extends EventEmitter
{
    /** @var bool */
    protected $debug;
    /** @var string|null */
    protected $cwd;
    /** @var FatalErrorHandlerInterface|null */
    protected $fatalHandler;
    /** @var bool */
    protected $cleanBuffers = true;
    /** @var object|null */
    protected $currentError;
    /** @var array|null */
    protected $lastError;
    /** @var bool */
    protected $registered = false;
    /** @var string|null */
    protected $previousDisplayErrorsSetting;
    /** @var bool */
    protected $shutdownHandlerRegistered = false;

    /**
     * @param bool        $debug debug mode 1/0
     * @param string|null $cwd   current working directory (null = getcwd())
     */
    public function __construct($debug = false, $cwd = null)
    {
        $this->debug = $debug;
        $this->cwd = $cwd ?: getcwd();

        // make sure the some classes are loaded as autoloading may
        // be unavailable during compile-time errors, {@see onError()}
        if (!class_exists('Kuria\Error\Util\Debug')) {
            require __DIR__ . '/Util/Debug.php';
        }
        if (!class_exists('Kuria\Error\ContextualErrorException')) {
            require __DIR__ . '/ContextualErrorException.php';
        }
    }

    /**
     * Register the error handler
     */
    public function register()
    {
        if (!$this->registered) {
            set_error_handler(array($this, 'onError'));
            set_exception_handler(array($this, 'onException'));

            $this->previousDisplayErrorsSetting = ini_get('display_errors');
            ini_set('display_errors', '0');
            
            $this->registered = true;

            if (!$this->shutdownHandlerRegistered) {
                register_shutdown_function(array($this, 'onShutdown'));
                $this->shutdownHandlerRegistered = true;
            }

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
            ini_set('display_errors', $this->previousDisplayErrorsSetting);

            $this->previousDisplayErrorsSetting = null;
            $this->registered = false;
        }
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
     * Get the fatal handler
     *
     * @return FatalErrorHandlerInterface
     */
    public function getFatalHandler()
    {
        if (null === $this->fatalHandler) {
            $this->fatalHandler = $this->getDefaultFatalHandler();
        }

        return $this->fatalHandler;
    }

    /**
     * @param FatalErrorHandlerInterface|null $fatalHandler
     * @return static
     */
    public function setFatalHandler(FatalErrorHandlerInterface $fatalHandler = null)
    {
        $this->fatalHandler = $fatalHandler;

        return $this;
    }

    /**
     * Set whether output buffers should be cleaned before
     * handling the fatal error handler is called.
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
     * @param array       $context variable context
     * @return bool
     */
    public function onError($code, $message, $file = null, $line = null, array $context = null)
    {
        $this->lastError = error_get_last();

        $this->currentError = null !== $context
            ? new ContextualErrorException($message, 0, $code, $file, $line, null, $context)
            : new \ErrorException($message, 0, $code, $file, $line)
        ;
        
        $suppressed = 0 === ($code & error_reporting());

        if ($this->hasAnyListeners('error')) {
            // make sure autoloading is active before emitting an event
            // (autoloading is inactive in some PHP versions during compile-time errors)
            // (the bug appears to have been fixed in PHP 5.4.21+, 5.5.5+ and 5.6.0+)
            // https://bugs.php.net/42098
            if (
                PHP_MAJOR_VERSION > 5 // PHP 7+
                || PHP_MINOR_VERSION >= 6 // PHP 5.6+
                || PHP_MINOR_VERSION === 5 && PHP_RELEASE_VERSION >= 5 // PHP 5.5.5+
                || PHP_MINOR_VERSION === 4 && PHP_RELEASE_VERSION >= 21 // PHP 5.4.21+
                || Debug::isAutoloadingActive()
            ) {
                $e = null;
                try {
                    $this->emitArray('error', array($this->currentError, $this->debug, &$suppressed));
                } catch (\Exception $e) {
                } catch (\Throwable $e) {
                }
                if (null !== $e) {
                    $this->currentError = Debug::joinExceptionChains($this->currentError, $e);
                    $suppressed = false;
                }
            }
        }

        if (!$suppressed) {
            $error = $this->currentError;
            $this->currentError = null;

            throw $error;
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
            // the fatal error could have happened during onError()
            // use the current error if it is not NULL
            $previous = $this->currentError;

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
     * @param object $exception
     */
    public function onException($exception)
    {
        $that = $this;
        $cwd = $this->cwd;
        $debug = $this->debug;
        
        $e = null;
        try {
            // fix working directory
            if (null !== $cwd) {
                chdir($cwd);
            }

            // handle the exception
            $fatalHandler = $that->getFatalHandler();
            
            $e2 = null;
            try {
                $this->emitArray('fatal', array($exception, $debug, &$fatalHandler));
            } catch (\Exception $e2) {
            } catch (\Throwable $e2) {
            }
            if (null !== $e2) {
                $exception = Debug::joinExceptionChains($exception, $e2);
            }

            if (null !== $fatalHandler) {
                $that->handleFatal($fatalHandler, $exception);
            }
        } catch (\Exception $e) {
        } catch (\Throwable $e) {
        }
        if (null !== $e) {
            $exception = Debug::joinExceptionChains($exception, $e);

            if ($this->hasAnyListeners('emerg')) {
                $this->emit('emerg', $exception, $this->debug);
            } elseif ($debug) {
                // debug mode is on and there is no emergency listener
                // just print the exception in this case (prevents white screen)
                echo $exception;
            }
        }
    }

    /**
     * Handle a fatal condition
     *
     * @param FatalErrorHandlerInterface $fatalHandler
     * @param object                     $exception
     */
    public function handleFatal(FatalErrorHandlerInterface $fatalHandler, $exception)
    {
        // replace headers
        if (!$this->isCli()) {
            Debug::replaceHeaders(array('HTTP/1.1 500 Internal Server Error'));
        }
        
        // clean output buffers
        $outputBuffer = $this->cleanBuffers ? Debug::cleanBuffers(null, true, true) : null;

        // handle
        $fatalHandler->handle($exception, $this->debug, $outputBuffer);
    }

    /**
     * Get the default fatal handler
     *
     * @return FatalErrorHandlerInterface
     */
    protected function getDefaultFatalHandler()
    {
        return $this->isCli() ? new CliErrorScreen() : new WebErrorScreen();
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
        if (null !== $this->currentError) {
            return true;
        } else {
            // ugly, but there is no get_error_handler()..
            $currentErrorHandler = set_error_handler(function () {}, 0);
            restore_error_handler();

            return is_array($currentErrorHandler) && $currentErrorHandler[0] === $this;
        }
    }
}
