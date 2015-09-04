<?php

namespace Kuria\Error\Screen;

use Kuria\Error\ContextualErrorException;

class WebErrorScreenTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param WebErrorScreen $screen
     * @param object         $exception
     * @param bool           $debug
     * @param string|null    $outputBuffer
     * @return string
     */
    private function doRender(WebErrorScreen $screen, $exception, $debug, $outputBuffer = 'foo bar output buffer')
    {
        ob_start();

        $screen->handle($exception, $debug, $outputBuffer);

        return ob_get_clean();
    }

    public function testRender()
    {
        $screen = new WebErrorScreen();

        $output = $this->doRender($screen, new \Exception('Test exception'), false);

        $this->assertContains('<h1>Internal server error</h1>', $output);
        $this->assertContains('Something went wrong', $output);
        $this->assertNotContains('Test exception', $output);
        $this->assertNotContains('foo bar output buffer', $output);
        $this->assertNotContains(basename(__FILE__), $output);
        $this->assertNotRegExp('~<ol[^>]+class="code-preview">~m', $output);
        $this->assertNotContains('class="context', $output);
        $this->assertNotContains('<table class="trace">', $output);
    }

    /**
     * @requires extension tidy
     */
    public function testRenderTidy()
    {
        $screen = new WebErrorScreen();

        $this->validateHtml($this->doRender($screen, new \Exception('Test exception'), false));
    }

    public function testRenderEvent()
    {
        $that = $this;
        $handlerCalled = false;

        $screen = new WebErrorScreen();

        $screen->on('render', function (array $event) use ($that, &$handlerCalled) {
            $that->assertFalse($handlerCalled);
            $that->assertRenderEvent($event, false);

            $event['title'] = 'Oh no';
            $event['heading'] = 'You broke everything';
            $event['text'] = '... and now I hate you.';
            $event['extras'] = 'custom content lorem ipsum';

            $handlerCalled = true;
        });

        $output = $this->doRender($screen, new \Exception('Test exception'), false);

        $this->assertTrue($handlerCalled);
        $this->assertContains('<title>Oh no</title>', $output);
        $this->assertContains('<h1>You broke everything</h1>', $output);
        $this->assertContains('... and now I hate you.', $output);
        $this->assertContains('custom content lorem ipsum', $output);
        $this->assertNotContains('Test exception', $output);
        $this->assertNotContains('foo bar output buffer', $output);
        $this->assertNotContains(basename(__FILE__), $output);
        $this->assertNotRegExp('~<ol[^>]+class="code-preview">~m', $output);
        $this->assertNotContains('class="context', $output);
        $this->assertNotContains('<table class="trace">', $output);
    }

    public function testDebugRender()
    {
        $screen = new WebErrorScreen();

        $output = $this->doRender($screen, $this->createTestException(), true);

        $this->assertRegExp('~<h1>.*User error.*</h1>~m', $output);
        $this->assertContains('Test exception', $output);
        $this->assertContains(basename(__FILE__), $output);
        $this->assertRegExp('~<ol[^>]+class="code-preview">~m', $output);
        $this->assertContains('class="context', $output);
        $this->assertContains('<table class="trace">', $output);
        $this->assertRegExp('~<h2>.*Exception.*</h2>~m', $output);
        $this->assertContains('Test nested exception', $output);
        $this->assertContains('foo bar output buffer', $output);
    }

    /**
     * @requires extension tidy
     */
    public function testDebugRenderTidy()
    {
        $screen = new WebErrorScreen();

        $this->validateHtml($this->doRender($screen, $this->createTestException(), true));
    }

    public function testDebugRenderLongOutputBuffer()
    {
        $screen = new WebErrorScreen();

        $screen->setMaxOutputBufferLength(50);

        $output = $this->doRender($screen, $this->createTestException(), true, str_repeat('x', 100));

        $this->assertContains('The output buffer is too big', $output);
    }

    public function testDebugRenderBinaryOutputBuffer()
    {
        $screen = new WebErrorScreen();

        $screen->setMaxOutputBufferLength(50);

        $output = $this->doRender($screen, $this->createTestException(), true, "hello\0world");

        $this->assertContains('The output buffer contains unprintable', $output);
    }

    public function testDebugRenderEmptyOutputBuffer()
    {
        $screen = new WebErrorScreen();

        $output = $this->doRender($screen, $this->createTestException(), true, '');

        $this->assertNotRegExp('~<h2.*Output buffer.*</h2>~m', $output);
    }

    public function testCodePreviewSizeLimit()
    {
        $screen = new WebErrorScreen();

        $screen->setMaxCodePreviewFileSize(0);

        $output = $this->doRender($screen, $this->createTestException(), true);

        $this->assertNotRegExp('~<ol[^>]+class="code-preview">~m', $output);
    }

    public function testDebugRenderEvent()
    {
        $that = $this;
        $handlerCalled = false;

        $screen = new WebErrorScreen();

        $screen->on('render.debug', function ($event) use ($that, &$handlerCalled) {
            $that->assertFalse($handlerCalled);
            $that->assertRenderEvent($event, true);

            $event['title'] = 'Oh no';
            $event['extras'] = 'custom content lorem ipsum';

            $handlerCalled = true;
        });

        $output = $this->doRender($screen, $this->createTestException(), true);

        $this->assertTrue($handlerCalled);
        $this->assertContains('<title>Oh no</title>', $output);
        $this->assertRegExp('~<h1>.*User error.*</h1>~m', $output);
        $this->assertContains('Test exception', $output);
        $this->assertContains(basename(__FILE__), $output);
        $this->assertRegExp('~<ol[^>]+class="code-preview">~m', $output);
        $this->assertContains('class="context', $output);
        $this->assertContains('<table class="trace">', $output);
        $this->assertContains('custom content lorem ipsum', $output);
        $this->assertRegExp('~<h2>.*Exception.*</h2>~m', $output);
        $this->assertContains('Test nested exception', $output);
        $this->assertRegExp('~<h2.*Output buffer.*</h2>~m', $output);
    }

    public function testLayoutEvents()
    {
        $this->doTestLayoutEvents(false);
    }

    public function testDebugLayoutEvents()
    {
        $this->doTestLayoutEvents(true);
    }

    private function doTestLayoutEvents($debugEnabled)
    {
        $that = $this;
        $cssHandlerCalled = false;
        $jsHandlerCalled = false;

        $screen = new WebErrorScreen();

        $screen
            ->on('layout.css', function ($event) use ($that, $debugEnabled, &$cssHandlerCalled) {
                $that->assertFalse($cssHandlerCalled);
                $that->assertAssetEvent($event, 'css', $debugEnabled);

                $event['css'] .= '/* my custom css */';

                $cssHandlerCalled = true;
            })
            ->on('layout.js', function ($event) use ($that, $debugEnabled, &$jsHandlerCalled) {
                $that->assertFalse($jsHandlerCalled);
                $that->assertAssetEvent($event, 'js', $debugEnabled);

                $event['js'] .= '/* my custom js */';

                $jsHandlerCalled = true;
            })
        ;

        $output = $this->doRender($screen, $this->createTestException(), $debugEnabled);

        $this->assertTrue($cssHandlerCalled);
        $this->assertTrue($jsHandlerCalled);
        $this->assertRegExp('~<style[^>]*>.*/\* my custom css \*/.*</style>~s', $output);
        $this->assertRegExp('~<script[^>]*>.*/\* my custom js \*/.*</script>~s', $output);
    }

    public function testCustomEncoding()
    {
        $encodedMessage = mb_convert_encoding('Желтый Верховая', 'KOI8-R', 'UTF-8');

        $screen = new WebErrorScreen();

        $screen
            ->setEncoding('KOI8-R')
            ->setHtmlCharset('KOI8-R')
        ;

        $output = $this->doRender($screen, $this->createTestException($encodedMessage), true);

        $this->assertContains('<meta charset="KOI8-R">', $output);
        $this->assertContains($encodedMessage, $output);
    }

    /**
     * @param string $html
     */
    private function validateHtml($html)
    {
        $tidy = tidy_parse_string($html, array(), 'utf8');
        $tidy->diagnose();

        if (preg_match('/^(\d+) warnings?, (\d+) errors? were found![\r\n]/m', $tidy->errorBuffer, $match)) {
            list(, $warningCount, $errorCount) = $match;

            $warningCount -= substr_count($tidy->errorBuffer, 'trimming empty');
            $warningCount -= substr_count($tidy->errorBuffer, 'proprietary attribute');
            $warningCount -= substr_count($tidy->errorBuffer, '<meta> lacks');
            $warningCount -= substr_count($tidy->errorBuffer, '<table> lacks "summary"');

            if ($warningCount > 0 || $errorCount > 0) {
                $this->fail($tidy->errorBuffer);
            }
        } else {
            $this->fail('could not parse tidy error buffer');
        }
    }

    /**
     * @return ContextualErrorException
     */
    private function createTestException($message = 'Test exception')
    {
        return new ContextualErrorException(
            $message,
            0,
            E_USER_ERROR,
            __FILE__,
            __LINE__,
            new \Exception('Test nested exception'),
            array('foo' => 'bar')
        );
    }

    public function assertRenderEvent($event, $debug)
    {
        $this->assertInternalType('array', $event);
        $this->assertArrayHasKey('title', $event);
        $this->assertArrayHasKey('extras', $event);
        $this->assertArrayHasKey('exception', $event);
        $this->assertArrayHasKey('output_buffer', $event);
        $this->assertArrayHasKey('screen', $event);

        $this->assertInternalType('string', $event['title']);
        $this->assertInternalType('string', $event['extras']);
        $this->assertInternalType('object', $event['exception']);
        $this->assertSame('foo bar output buffer', $event['output_buffer']);
        $this->assertInstanceOf(__NAMESPACE__ . '\WebErrorScreen', $event['screen']);

        if (!$debug) {
            $this->assertArrayHasKey('heading', $event);
            $this->assertArrayHasKey('text', $event);

            $this->assertInternalType('string', $event['heading']);
            $this->assertInternalType('string', $event['text']);
        }
    }

    public function assertAssetEvent($event, $type, $debug)
    {
        $this->assertInternalType('array', $event);
        $this->assertArrayHasKey($type, $event);
        $this->assertArrayHasKey('debug', $event);
        $this->assertArrayHasKey('screen', $event);

        $this->assertInternalType('string', $event[$type]);
        $this->assertInternalType('boolean', $event['debug']);
        $this->assertInstanceOf(__NAMESPACE__ . '\WebErrorScreen', $event['screen']);
        $this->assertSame($debug, $event['debug']);
    }
}
