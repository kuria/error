Changelog
#########

4.2.0
*****

- cli error screen now displays the exception message even in non-debug mode
- web error screen trace frames are no longer expandable if there is nothing to be displayed


4.1.0
*****

- updated kuria/debug dependency to v4


4.0.0
*****

- changed most class members from protected to private
- cs fixes, added codestyle checks


3.0.0
*****

- web error screen improvements

  - improved styles
  - added favicon (debug only)
  - added viewport meta tag
  - moved CSS and JS to separate files
  - simplified CSS and JS events

- moved ``PhpCodePreview`` into its own component


2.0.0
*****

- updated to PHP 7.1
- added custom exception classes
- code style improvements


1.0.1
*****

- code style and test improvements


1.0.0
*****

- refactoring
- PHP 7.1 & 7.2 compatibility
- implemented a memory-reserving mechanism to improve handling of out-of-memory errors
- disabled output buffer capturing when out of memory
- disabled PHP code preview when out of memory
- implemented error types
- added intermediate exceptions when exceptions are chained
- binary output buffer is now rendered in HEX format
- long lines now break over multiple lines in PHP code preview
- the debug utility has been moved to a separate component


0.2.0
*****

- PHP 7 support
- updated dependencies
- implemented variable contexts
- expandable sections in the web error screen
- improved styles of the web error screen
- debug utility improvements
- added more tests


0.1.1
*****

- minor improvements


0.1.0
*****

Initial release
