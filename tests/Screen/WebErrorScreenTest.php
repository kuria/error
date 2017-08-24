<?php declare(strict_types=1);

namespace Kuria\Error\Screen;

use Kuria\Error\Exception\ErrorException;
use PHPUnit\Framework\TestCase;

class WebErrorScreenTest extends TestCase
{
    function testRender()
    {
        $screen = new WebErrorScreen();

        $output = $this->doRender($screen, new \Exception('Test exception'), false);

        $this->assertContains('<h1>Internal server error</h1>', $output);
        $this->assertContains('Something went wrong', $output);
        $this->assertNotContains('Test exception', $output);
        $this->assertNotContains('foo bar output buffer', $output);
        $this->assertNotContains(basename(__FILE__), $output);
        $this->assertNotRegExp('{<ol[^>]+class="code-preview">}m', $output);
        $this->assertNotContains('<table class="trace">', $output);
    }

    function testRenderEvent()
    {
        $handlerCalled = false;

        $screen = new WebErrorScreen();

        $screen->on(WebErrorScreenEvents::RENDER, function (array $event) use (&$handlerCalled) {
            $this->assertFalse($handlerCalled);
            $this->assertRenderEvent($event, false);

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
        $this->assertNotRegExp('{<ol[^>]+class="code-preview">}m', $output);
        $this->assertNotContains('<table class="trace">', $output);
    }

    function testDebugRender()
    {
        $screen = new WebErrorScreen();

        $output = $this->doRender($screen, $this->createTestException(), true);

        $this->assertRegExp('{<h1>.*User error.*</h1>}m', $output);
        $this->assertContains('Test exception', $output);
        $this->assertContains(basename(__FILE__), $output);
        $this->assertRegExp('{<ol[^>]+class="code-preview">}m', $output);
        $this->assertContains('<table class="trace">', $output);
        $this->assertRegExp('{<h2>.*Exception.*</h2>}m', $output);
        $this->assertContains('Test nested exception', $output);
        $this->assertContains('foo bar output buffer', $output);
    }


    function testDebugRenderLongOutputBuffer()
    {
        $screen = new WebErrorScreen();

        $screen->setMaxOutputBufferLength(50);

        $output = $this->doRender($screen, $this->createTestException(), true, str_repeat('x', 100));

        $this->assertContains('The output buffer is too big', $output);
    }

    function testDebugRenderBinaryOutputBuffer()
    {
        $screen = new WebErrorScreen();

        $screen->setMaxOutputBufferLength(50);

        $output = $this->doRender($screen, $this->createTestException(), true, "hello\0world");

        $this->assertContains('The output buffer contains unprintable', $output);
    }

    function testDebugRenderEmptyOutputBuffer()
    {
        $screen = new WebErrorScreen();

        $output = $this->doRender($screen, $this->createTestException(), true, '');

        $this->assertNotRegExp('{<h2.*Output buffer.*</h2>}m', $output);
    }

    function testCodePreviewSizeLimit()
    {
        $screen = new WebErrorScreen();

        $screen->setMaxCodePreviewFileSize(0);

        $output = $this->doRender($screen, $this->createTestException(), true);

        $this->assertNotRegExp('{<ol[^>]+class="code-preview">}m', $output);
    }

    function testDebugRenderEvent()
    {
        $handlerCalled = false;

        $screen = new WebErrorScreen();

        $screen->on(WebErrorScreenEvents::RENDER_DEBUG, function ($event) use (&$handlerCalled) {
            $this->assertFalse($handlerCalled);
            $this->assertRenderEvent($event, true);

            $event['title'] = 'Oh no';
            $event['extras'] = 'custom content lorem ipsum';

            $handlerCalled = true;
        });

        $output = $this->doRender($screen, $this->createTestException(), true);

        $this->assertTrue($handlerCalled);
        $this->assertContains('<title>Oh no</title>', $output);
        $this->assertRegExp('{<h1>.*User error.*</h1>}m', $output);
        $this->assertContains('Test exception', $output);
        $this->assertContains(basename(__FILE__), $output);
        $this->assertRegExp('{<ol[^>]+class="code-preview">}m', $output);
                $this->assertContains('<table class="trace">', $output);
        $this->assertContains('custom content lorem ipsum', $output);
        $this->assertRegExp('{<h2>.*Exception.*</h2>}m', $output);
        $this->assertContains('Test nested exception', $output);
        $this->assertRegExp('{<h2.*Output buffer.*</h2>}m', $output);
    }

    function testLayoutEvents()
    {
        $this->doTestLayoutEvents(false);
    }

    function testDebugLayoutEvents()
    {
        $this->doTestLayoutEvents(true);
    }

    private function doTestLayoutEvents(bool $debugEnabled): void
    {
        $cssHandlerCalled = false;
        $jsHandlerCalled = false;

        $screen = new WebErrorScreen();

        $screen->on(WebErrorScreenEvents::LAYOUT_CSS, function ($event) use ($debugEnabled, &$cssHandlerCalled) {
            $this->assertFalse($cssHandlerCalled);
            $this->assertAssetEvent($event, 'css', $debugEnabled);

            $event['css'] .= '/* my custom css */';

            $cssHandlerCalled = true;
        });

        $screen->on(WebErrorScreenEvents::LAYOUT_JS, function ($event) use ($debugEnabled, &$jsHandlerCalled) {
            $this->assertFalse($jsHandlerCalled);
            $this->assertAssetEvent($event, 'js', $debugEnabled);

            $event['js'] .= '/* my custom js */';

            $jsHandlerCalled = true;
        });

        $output = $this->doRender($screen, $this->createTestException(), $debugEnabled);

        $this->assertTrue($cssHandlerCalled);
        $this->assertTrue($jsHandlerCalled);
        $this->assertRegExp('{<style[^>]*>.*/\* my custom css \*/.*</style>}s', $output);
        $this->assertRegExp('{<script[^>]*>.*/\* my custom js \*/.*</script>}s', $output);
    }

    function testCustomEncoding()
    {
        $encodedMessage = mb_convert_encoding('Желтый Верховая', 'KOI8-R', 'UTF-8');

        $screen = new WebErrorScreen();

        $screen->setEncoding('KOI8-R');
        $screen->setHtmlCharset('KOI8-R');

        $output = $this->doRender($screen, $this->createTestException($encodedMessage), true);

        $this->assertContains('<meta charset="KOI8-R">', $output);
        $this->assertContains($encodedMessage, $output);
    }

    private function createTestException(string $message = 'Test exception'): ErrorException
    {
        return new ErrorException(
            $message,
            E_USER_ERROR,
            false,
            __FILE__,
            __LINE__,
            new \Exception('Test nested exception')
        );
    }

    function assertRenderEvent($event, bool $debug): void
    {
        $this->assertInternalType('array', $event);
        $this->assertArrayHasKey('title', $event);
        $this->assertArrayHasKey('extras', $event);
        $this->assertArrayHasKey('exception', $event);
        $this->assertArrayHasKey('output_buffer', $event);

        $this->assertInternalType('string', $event['title']);
        $this->assertInternalType('string', $event['extras']);
        $this->assertInternalType('object', $event['exception']);
        $this->assertSame('foo bar output buffer', $event['output_buffer']);

        if (!$debug) {
            $this->assertArrayHasKey('heading', $event);
            $this->assertArrayHasKey('text', $event);

            $this->assertInternalType('string', $event['heading']);
            $this->assertInternalType('string', $event['text']);
        }
    }

    function assertAssetEvent($event, string $type, bool $debug): void
    {
        $this->assertInternalType('array', $event);
        $this->assertArrayHasKey($type, $event);
        $this->assertArrayHasKey('debug', $event);

        $this->assertInternalType('string', $event[$type]);
        $this->assertInternalType('boolean', $event['debug']);
        $this->assertSame($debug, $event['debug']);
    }

    private function doRender(WebErrorScreen $screen, \Throwable $exception, bool $debug, ?string $outputBuffer = 'foo bar output buffer'): string
    {
        ob_start();

        $screen->render($exception, $debug, $outputBuffer);

        return ob_get_clean();
    }
}
