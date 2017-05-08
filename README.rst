Error handler
#############

Makes handling and debugging PHP errors suck less.

|Web error screen in debug mode|

.. contents::


Features
********

-  debug and non-debug mode
-  converts PHP errors (warnings, notices, etc) into exceptions

   -  respects the global ``error_reporting`` setting

-  handles uncaught exceptions and fatal errors (including parse errors)
-  CLI error screen (writes errors to STDERR)
-  web error screen (renders errors for web browsers)

   - | non-debug mode:
     | |Web error screen in non-debug mode|

     - simple error message
       does not disclose any internal information

   - | debug mode:
     | |Web error screen in debug mode|

     - file paths and line numbers
     - highlighted code previews
     - stack traces
     - argument lists
     - output buffer (can be shown as HTML too)
     - plaintext trace (for copy-paste)

-  event system that can be utilised to:

   -  implement logging
   -  suppress or force errors conditionally
   -  change or add content to the error screens


Requirements
************

-  PHP 5.3.0+ or 7.0.0+


Usage example
*************

.. code:: php

   <?php

   use Kuria\Error\ErrorHandler;

   $debug = true; // true during development, false in production
   error_reporting(E_ALL | E_STRICT); // configure the error reporting

   $errorHandler = new ErrorHandler($debug);
   $errorHandler->register();

   // trigger an error to see the error handler in action
   echo $invalidVariable;


Event system
************

-  implemented using the `kuria/event <https://github.com/kuria/event>`_ library
-  the error handler fires events as it handles errors
-  both built-in error screen implementations emit events as they render


Error handler events
====================

Possible events emitted by the ``ErrorHandler`` class:


``error``
---------

-  emitted when a PHP errors occurs
-  arguments:

   1. ``object $exception``

      -  instance of ``ErrorException``

   2. ``bool $debug``
   3. ``bool &$suppressed``

      -  reference to the suppressed state of the error
      -  the error can be suppressed by current ``error_reporting`` configuration or by other event handlers


``fatal``
---------

-  emitted when an uncaught exception or a fatal error is being handled
-  arguments:

   1. ``object $exception``
   2. ``bool $debug``
   3. ``FatalErrorHandlerInterface &$handler``

      -  reference to the current fatal error handler


``emerg``
---------

-  emitted when another exceptions has been thrown during fatal error handling
-  more uncaught exceptions or a fatal error at this point will just kill the script
-  arguments:

   1. ``object $exception``
   2. ``bool $debug``


Web error screen events
=======================

Possible events emitted by the ``WebErrorScreen`` class:


``render``
----------

-  emitted when rendering in **non-debug mode**
-  single argument - an event array with the following keys:

   -  ``&title``: used in ``<title>``
   -  ``&heading``: used in ``<h1>``
   -  ``&text``: content of the default paragraph
   -  ``&extras``: custom HTML after the main section
   -  ``exception``: the exception
   -  ``output_buffer``: string\|null
   -  ``screen``: instance of ``WebErrorScreen``


``render.debug``
----------------

-  emitted when rendering in **debug mode**
-  single argument - an event array with the following keys:

   -  ``&title``: used in ``<title>``
   -  ``&extras``: custom HTML after the main section
   -  ``exception``: the exception
   -  ``output_buffer``: string\|null
   -  ``screen``: instance of ``WebErrorScreen``


``layout.css``
--------------

-  emitted when CSS styles are being output
-  single argument - an event array with the following keys:

   -  ``&css``: the CSS output
   -  ``debug``: boolean
   -  ``screen``: instance of ``WebErrorScreen``


``layout.js``
-------------

-  emitted when JavaScript code is being output
-  single argument - an event array with the following keys:

   -  ``&js``: the JS output
   -  ``debug``: boolean
   -  ``screen``: instance of ``WebErrorScreen``


CLI error screen events
=======================

Possible events emitted by the ``CliErrorScreen`` class:


render
------

-  emitted when rendering in non-debug mode
-  single argument - an event array with the following keys:

   -  ``&title``: first line of output
   -  ``&output``: error message
   -  ``exception``: the exception
   -  ``output_buffer``: string\|null
   -  ``screen``: instance of ``WebErrorScreen``

render.debug
------------

-  emitted when rendering in debug mode
-  single argument - an event array with the following keys:

   -  ``&title``: first line of output
   -  ``&output``: error message
   -  ``exception``: the exception
   -  ``output_buffer``: string\|null
   -  ``screen``: instance of ``WebErrorScreen``


Event listener examples
=======================

Notes
-----

-  do not typehint the ``Exception`` class in your listeners if you want to be compatible with the new exception hierarchy of PHP 7


Logging
-------

Logging unhandled errors into a file.

.. code:: php

   <?php

   use Kuria\Error\Util\Debug;

   $errorHandler->on('fatal', function ($exception, $debug) {
       $logFilePath = sprintf('./errors_%s.log', $debug ? 'debug' : 'prod');

       $entry = sprintf(
           "[%s] %s - %s in file %s on line %d\n",
           date('Y-m-d H:i:s'),
           Debug::getExceptionName($exception),
           $exception->getMessage(),
           $exception->getFile(),
           $exception->getLine()
       );

       file_put_contents($logFilePath, $entry, FILE_APPEND | LOCK_EX);
   });


Disabling the "@" operator
--------------------------

This listener causes statements like ``echo @$invalidVariable;`` to throw an exception regardless of the "shut-up" operator.

.. code:: php

   <?php

   $errorHandler->on('error', function ($exception, $debug, &$suppressed) {
       $suppressed = false;
   });


Altering the error screens
--------------------------

Examples for the web error screen.

Changing default labels of the non-debug error screen:

.. code:: php

   <?php

   use Kuria\Error\Screen\WebErrorScreen;

   $errorHandler->on('fatal', function ($exception, $debug, $screen) {
      if (!$debug && $screen instanceof WebErrorScreen) {
           $screen->on('render', function ($event) {
               $event['heading'] = 'It is all your fault!';
               $event['text'] = 'You have broken everything and now I hate you.';
           });
       }
   });


Adding a customized section to the debug screen:

.. code:: php

   <?php

   use Kuria\Error\Screen\WebErrorScreen;

   $errorHandler->on('fatal', function ($exception, $debug, $screen) {
      if ($debug && $screen instanceof WebErrorScreen) {
           $screen
               ->on('layout.css', function ($event) {
                   $event['css'] .= '#custom-group {color: #f60000;}';
               })
               ->on('render.debug', function ($event) {
                   $event['extras'] .= <<<HTML
   <div id="custom-group" class="group">
       <div class="section">
           Example of a custom section
       </div>
   </div>
   HTML;
               })
           ;
       }
   });


.. |Web error screen in non-debug mode| image:: http://static.shira.cz/kuria/error/v1.0.x/non-debug-thumb.gif
   :target: http://static.shira.cz/kuria/error/v1.0.x/non-debug.png
.. |Web error screen in debug mode| image:: http://static.shira.cz/kuria/error/v1.0.x/debug-thumb.gif
   :target: http://static.shira.cz/kuria/error/v1.0.x/debug.png
