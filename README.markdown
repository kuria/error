PHP error handler
=================

Makes PHP errors suck less.

## Features

- handles errros
    - fatal errors
    - uncaught exceptions
    - throws PHP errors as `ErrorException`s
    - respects current `error_reporting` setting
    - handles several "gotcha" cases
- configuration
    - debug mode on / off
         - should be enabled during development
         - should be disabled in production
- error screens
    - renders error messages
    - works both in web and CLI environments (e.g. a terminal)
    - web renderer
        - in debug mode: messages, files, stack traces, argument lists, code preview, output buffer
        - non-debug mode: generic message
        - can be manipulated through events
            - adding custom html, css and js
            - changing non-debug mode title and message
    - CLI renderer
        - in debug mode: messages, files, stack traces
        - non-debug mode: generic message
        - writes to `STDERR`
- event system
    - events fire for both runtime and fatal erros
    - events can alter behavior of the error handler
    - ideal to hook your logic (like logging, see examples)
    - additional errors from the observers do not break the error handler nor hide errors


## Requirements

- PHP 5.3 or newer


## Usage example

    use Kuria\Error\ErrorHandler;

    $debug = true; // true in development, false in production

    $errorHandler = new ErrorHandler($debug);
    $errorHandler->register();

    // trigger an error to see the error handler in action
    echo $invalidVariable;


## Event system

The error handler fires events when errors happen. This is implemented using
the [kuria/event](https://github.com/kuria/event) library.

The `ErrorHandler` extends `ExternalObservable`. This means:

- you can attach observers directly to the error handler instance
- you can replace the underlying observable instance by using `ErrorHandler->setNestedObservable()`


### Event class

All events sent by the error handler are an instance of `Kuria\Error\ErrorHandlerEvent`.

The event provides many methods to read information about the error and modify behavior
of the error handler.

Please refer to `src/ErrorHandlerEvent.php` to see all available methods.


### Event names

- `ErrorHandlerEvent::RUNTIME`
    - fired for runtime errors that should throw an `ErrorException`
- `ErrorHandlerEvent::RUNTIME_SUPPRESSED`
    - fired for runtime errors that are suppressed (by `error_reporting` setting or the `@` operator)
- `ErrorHandlerEvent::FATAL`
    - fired for uncaught exceptions and fatal errors


### Listener examples


#### Error logging

Logging fatal errors into a text file.

    use Kuria\Error\ErrorHandlerEvent;
    use Kuria\Error\DebugUtils;

    $errorHandler->addListener(ErrorHandlerEvent::FATAL, function (ErrorHandlerEvent $event) {
        $logFileName = sprintf('./errors_%s.log', $event->getDebug() ? 'debug' : 'prod');

        $exception = $event->getException();

        $entry = sprintf(
            "[%s] %s - %s in file %s on line %d\n",
            date('Y-m-d H:i:s'),
            DebugUtils::getExceptionName($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        file_put_contents($logFileName, $entry, FILE_APPEND);
    });


#### Disabling the "@" operator

This listener causes statements like `echo @$invalidVariable;` to throw an exception regardless
of the "shut-up" operator.

    use Kuria\Error\ErrorHandlerEvent;

    $errorHandler->addListener(ErrorHandlerEvent::RUNTIME_SUPPRESSED, function (ErrorHandlerEvent $event) {
        if (0 === error_reporting()) {
            $event->forceRuntimeException();
        }
    });


#### Altering the error screen

The error screen can be modified right before it is rendered.

Please refer to `src/WebExceptionRenderer.php` to see all public properties that can be modified.

    use Kuria\Error\ErrorHandlerEvent;
    use Kuria\Error\WebExceptionRenderer;

    $errorHandler->addListener(ErrorHandlerEvent::FATAL, function (ErrorHandlerEvent $event) {
        $renderer = $event->getRenderer();

        if ($renderer instanceof WebExceptionRenderer) {
            if ($event->getDebug()) {
                // debug mode
                $renderer->extraCss .= '#my-section {background-color: red; color: white;}';
                $renderer->extraHtml .= '<div id="my-section" class="section minor standalone">Example custom section</div>';
            } else {
                // non-debug mode
                $renderer->title = 'It is all your fault!';
                $renderer->text = 'You have broken everything and now I hate you.';
            }
        }
    });


#### Disabling the error screen renderer

If you want to disable any error output for whatever reason.

    use Kuria\Error\ErrorHandlerEvent;

    $errorHandler->addListener(ErrorHandlerEvent::FATAL, function (ErrorHandlerEvent $event) {
        $event->disableRenderer();
    });


#### Replacing the error screen renderer

Adding CSS and HTML may not be enough. In that case you are free to implement your own renderer.

**Note**: exceptions thrown from the `render()` method will not be displayed, but passed
to the emergency handler callback (if any). See the emergency handler section far below.

    use Kuria\Error\ErrorHandlerEvent;
    use Kuria\Error\WebExceptionRenderer;
    use Kuria\Error\ExceptionRendererInterface;

    class MyCustomRenderer implements ExceptionRendererInterface
    {
        public function render($debug, \Exception $exception, $outputBuffer = null)
        {
            echo "There was an error :( It said: {$exception->getMessage()}";
        }
    }

    $errorHandler->addListener(ErrorHandlerEvent::FATAL, function (ErrorHandlerEvent $event) {
        if ($event->getRenderer() instanceof WebExceptionRenderer) {
            $event->replaceRenderer(new MyCustomRenderer());
        }
    });


### Autoloading during compile-time errors

Autoloading may not be available during compile-time errors. This is a limitation
of PHP and supposedly "not a bug".

- [https://bugs.php.net/bug.php?id=42098](https://bugs.php.net/bug.php?id=42098)
- [https://bugs.php.net/bug.php?id=54054](https://bugs.php.net/bug.php?id=54054)

Implications:

- this does not affect fatal error listeners (`ErrorHandlerEvent::FATAL`)
- if your listener does not rely on autoloading, you are safe
- if your listener needs autoloading, this could result in a "Class '...' not found" fatal error
    - you can call `Kuria\Error\DebugUtils::isAutoloadingActive()` and act accordingly
    - the error handler is smart enough to handle this case and shows both the fatal error and the original error


## Emergency handler

Exceptions thrown during rendering of an error are not be displayed. They are caught and passed to the
configured emergency handler callback (if any). This should not happen, unless there is a bug
in the current renderer implementation.


- to register an emergency handler callback, use the `ErrorHandler->setEmergencyHandler()` method
- the callback is passed a single argument - an `Exception` instance
    - the exception contains all previous exceptions chained together
