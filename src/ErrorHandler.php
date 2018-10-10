<?php declare(strict_types=1);

namespace Kuria\Error;

use Kuria\Debug\Exception;
use Kuria\Debug\Output;
use Kuria\Error\Exception\ChainedException;
use Kuria\Error\Exception\ErrorException;
use Kuria\Error\Exception\FatalErrorException;
use Kuria\Error\Exception\OutOfMemoryException;
use Kuria\Error\Screen\CliErrorScreen;
use Kuria\Error\Screen\WebErrorScreen;
use Kuria\Event\Observable;

/**
 * @see ErrorHandlerEvents
 */
class ErrorHandler extends Observable
{
    /** @var bool */
    private $debug = false;

    /** @var bool */
    private $printUnhandledExceptionInDebug = true;

    /** @var string|null */
    private $workingDirectory;

    /** @var ErrorScreenInterface */
    private $errorScreen;

    /** @var bool */
    private $cleanBuffers = true;

    /** @var ErrorException|null */
    private $currentErrorException;

    /** @var array|null */
    private $lastError;

    /** @var bool */
    private $registered = false;

    /** @var string|null */
    private $previousDisplayErrorsSetting;

    /** @var bool */
    private $shutdownHandlerRegistered = false;

    /** @var string|null */
    private $reservedMemory;

    function __construct(?ErrorScreenInterface $errorScreen = null, $reservedMemoryBytes = 10240)
    {
        $this->errorScreen = $errorScreen ?? $this->getDefaultErrorScreen();
        $this->reservedMemory = $reservedMemoryBytes > 0 ? str_repeat('.', $reservedMemoryBytes) : null;
        $this->workingDirectory = getcwd();

        class_exists(OutOfMemoryException::class); // preload
    }

    function register(): void
    {
        if (!$this->registered) {
            set_error_handler([$this, 'onError']);
            set_exception_handler([$this, 'onUncaughtException']);

            $this->previousDisplayErrorsSetting = ini_get('display_errors');
            ini_set('display_errors', '0');

            $this->registered = true;

            if (!$this->shutdownHandlerRegistered) {
                register_shutdown_function([$this, 'onShutdown']);
                $this->shutdownHandlerRegistered = true;
            }

            // store last known error
            // this prevents a bogus fatal error on shutdown if an error has already happened
            $this->lastError = error_get_last();
        }
    }

    function unregister(): void
    {
        if ($this->registered) {
            set_error_handler(null);
            set_exception_handler(null);
            ini_set('display_errors', $this->previousDisplayErrorsSetting);

            $this->previousDisplayErrorsSetting = null;
            $this->registered = false;
        }
    }

    function isDebugEnabled(): bool
    {
        return $this->debug;
    }

    function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * Set whether unhandled exceptions should be printed to the screen in debug mode
     *
     * Unhandled exceptions can occur when an additional exception is thrown from
     * an event listener or the error screen implementation.
     */
    function setPrintUnhandledExceptionInDebug(bool $printUnhandledExceptionInDebug): void
    {
        $this->printUnhandledExceptionInDebug = $printUnhandledExceptionInDebug;
    }

    /**
     * Set working directory to use when handling fatal errors
     *
     * - this is useful because some SAPIs may change the working directory inside shutdown functions
     * - set to NULL to keep the working directory unchanged (not recommended)
     */
    function setWorkingDirectory(?string $workingDirectory): void
    {
        $this->workingDirectory = $workingDirectory;
    }

    function getErrorScreen(): ErrorScreenInterface
    {
        return $this->errorScreen;
    }

    function setErrorScreen(ErrorScreenInterface $errorScreen): void
    {
        $this->errorScreen = $errorScreen;
    }

    /**
     * Set whether output buffers should be cleaned before the error screen is invoked
     */
    function setCleanBuffers(bool $cleanBuffers): void
    {
        $this->cleanBuffers = $cleanBuffers;
    }

    /**
     * Handle a PHP error
     *
     * @throws ErrorException if the error isn't suppressed
     * @throws ChainedException if an event listener throws an exception
     */
    function onError(int $code, string $message, ?string $file = null, ?int $line = null): bool
    {
        $this->lastError = error_get_last();
        $this->currentErrorException = new ErrorException(
            $message,
            $code,
            ($code & error_reporting()) === 0,
            $file ?? __FILE__,
            $line ?? __LINE__
        );

        try {
            try {
                $this->emit(ErrorHandlerEvents::ERROR, $this->currentErrorException, $this->debug);
            } catch (\Throwable $e) {
                throw new ChainedException(
                    'Additional exception was thrown from an [error] event listener. See previous exceptions.',
                    0,
                    Exception::joinChains($this->currentErrorException, $e)
                );
            }

            // check suppression state
            if (!$this->currentErrorException->isSuppressed()) {
                throw $this->currentErrorException;
            }

            // suppressed error
            return true;
        } finally {
            $this->currentErrorException = null;
        }
    }

    /**
     * Handle an uncaught exception
     */
    function onUncaughtException(\Throwable $exception): void
    {
        try {
            // handle the exception
            try {
                $this->emit(ErrorHandlerEvents::EXCEPTION, $exception, $this->debug);
            } catch (\Throwable $e) {
                $exception = new ChainedException(
                    'Additional exception was thrown from an [exception] event listener. See previous exceptions.',
                    0,
                    Exception::joinChains($exception, $e)
                );
            }

            $this->invokeErrorScreen($exception);

            return;
        } catch (\Throwable $e) {
            $exception = new ChainedException(
                sprintf('Additional exception was thrown while trying to invoke %s. See previous exceptions.', get_class($this->errorScreen)),
                0,
                Exception::joinChains($exception, $e)
            );

            $this->emit(ErrorHandlerEvents::FAILURE, $exception, $this->debug);
        }

        // unhandled exception
        if ($this->debug && $this->printUnhandledExceptionInDebug) {
            echo Exception::render($exception, true, true);
        }
    }

    /**
     * Check for a fatal error on shutdown
     *
     * @internal
     */
    function onShutdown(): void
    {
        // free the reserved memory
        $this->reservedMemory = null;

        if (
            $this->isActive()
            && ($error = error_get_last()) !== null
            && $error !== $this->lastError
        ) {
            $this->lastError = null;

            // fix working directory
            if ($this->workingDirectory !== null) {
                chdir($this->workingDirectory);
            }

            // determine exception class
            if ($this->isOutOfMemoryError($error)) {
                gc_collect_cycles();
                $exceptionClass = OutOfMemoryException::class;
            } else {
                $exceptionClass = FatalErrorException::class;
            }

            // handle
            $this->onUncaughtException(
                new $exceptionClass(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line'],
                    $this->currentErrorException // use current error exception if a fatal error happens during onError()
                )
            );
        }
    }

    protected function getDefaultErrorScreen(): ErrorScreenInterface
    {
        return $this->isCli() ? new CliErrorScreen() : new WebErrorScreen();
    }

    protected function invokeErrorScreen(\Throwable $exception): void
    {
        // replace headers
        if (!$this->isCli()) {
            Output::replaceHeaders(['HTTP/1.1 500 Internal Server Error']);
        }

        // clean output buffers
        $outputBuffer = null;

        if ($this->cleanBuffers) {
            // capture output buffers unless out of memory
            if (!$exception instanceof OutOfMemoryException) {
                $outputBuffer = Output::captureBuffers(null, true);
            } else {
                Output::cleanBuffers(null, true);
            }
        }

        // render
        $this->errorScreen->render($exception, $this->debug, $outputBuffer);
    }

    protected function isCli(): bool
    {
        return PHP_SAPI === 'cli';
    }

    protected function isOutOfMemoryError(array $error): bool
    {
        return $error['type'] === E_ERROR && (
            strncasecmp($error['message'], 'Allowed memory size of ', 23) === 0
            || strncasecmp($error['message'], 'Out of memory', 13) === 0
        );
    }

    /**
     * See if this is the current error handler
     */
    protected function isActive(): bool
    {
        if ($this->currentErrorException !== null) {
            return true;
        }

        // ugly, but there is no get_error_handler()
        $currentErrorHandler = set_error_handler(function () {});
        restore_error_handler();

        return is_array($currentErrorHandler) && $currentErrorHandler[0] === $this;
    }
}
