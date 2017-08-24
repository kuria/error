<?php declare(strict_types=1);

namespace Kuria\Error\Screen;

use PHPUnit\Framework\TestCase;

class CliErrorScreenTest extends TestCase
{
    function testRender()
    {
        $screen = new CliErrorScreen();

        $output = $this->doRender($screen, new \Exception('Test exception'), false);

        $this->assertContains('An error has occured', $output);
        $this->assertContains('Enable debug mode for more details', $output);
        $this->assertNotContains('Test exception', $output);
    }

    function testRenderEvent()
    {
        $handlerCalled = false;

        $screen = new CliErrorScreen();

        $screen->on(CliErrorScreenEvents::RENDER, function ($event) use (&$handlerCalled) {
            $this->assertFalse($handlerCalled);
            $this->assertRenderEvent($event);

            $event['title'] = 'Lorem ipsum';
            $event['output'] .= "\n\nDolor sit amet";

            $handlerCalled = true;
        });

        $output = $this->doRender($screen, new \Exception('Test exception'), false);

        $this->assertTrue($handlerCalled);
        $this->assertNotContains('An error has occured', $output);
        $this->assertNotContains('Test exception', $output);
        $this->assertContains('Enable debug mode for more details', $output);
        $this->assertContains('Lorem ipsum', $output);
        $this->assertContains('Dolor sit amet', $output);
    }

    function testRenderEventCustomOutput()
    {
        $handlerCalled = false;

        $screen = new CliErrorScreen();

        $screen->on(CliErrorScreenEvents::RENDER, function ($event) use ( &$handlerCalled) {
            $this->assertFalse($handlerCalled);
            $this->assertRenderEvent($event);

            $event['title'] = 'Lorem ipsum';
            $event['output'] = 'Dolor sit amet';

            $handlerCalled = true;
        });

        $output = $this->doRender($screen, new \Exception('Test exception'), false);

        $this->assertTrue($handlerCalled);
        $this->assertNotContains('An error has occured', $output);
        $this->assertNotContains('Test exception', $output);
        $this->assertNotContains('Enable debug mode for more details', $output);
        $this->assertContains('Lorem ipsum', $output);
        $this->assertContains('Dolor sit amet', $output);
    }

    function testDebugRender()
    {
        $screen = new CliErrorScreen();

        $output = $this->doRender($screen, new \Exception('Test exception'), true);

        $this->assertContains('An error has occured', $output);
        $this->assertContains('Test exception', $output);
    }

    function testDebugRenderEvent()
    {
        $handlerCalled = false;

        $screen = new CliErrorScreen();

        $screen->on(CliErrorScreenEvents::RENDER_DEBUG, function ($event) use (&$handlerCalled) {
            $this->assertFalse($handlerCalled);
            $this->assertRenderEvent($event);

            $event['title'] = 'Lorem ipsum';
            $event['output'] .= "\n\nDolor sit amet";

            $handlerCalled = true;
        });

        $output = $this->doRender($screen, new \Exception('Test exception'), true);

        $this->assertTrue($handlerCalled);
        $this->assertNotContains('An error has occured', $output);
        $this->assertContains('Lorem ipsum', $output);
        $this->assertContains('Test exception', $output);
        $this->assertContains('Dolor sit amet', $output);
    }

    function testDebugRenderEventCustomOutput()
    {
        $handlerCalled = false;

        $screen = new CliErrorScreen();

        $screen->on(CliErrorScreenEvents::RENDER_DEBUG, function ($event) use (&$handlerCalled) {
            $this->assertFalse($handlerCalled);
            $this->assertRenderEvent($event);

            $event['title'] = 'Lorem ipsum';
            $event['output'] = 'Dolor sit amet';

            $handlerCalled = true;
        });

        $output = $this->doRender($screen, new \Exception('Test exception'), true);

        $this->assertTrue($handlerCalled);
        $this->assertNotContains('An error has occured', $output);
        $this->assertNotContains('Test exception', $output);
        $this->assertContains('Lorem ipsum', $output);
        $this->assertContains('Dolor sit amet', $output);
    }

    function assertRenderEvent($event): void
    {
        $this->assertInternalType('array', $event);
        $this->assertArrayHasKey('title', $event);
        $this->assertArrayHasKey('output', $event);
        $this->assertArrayHasKey('exception', $event);
        $this->assertArrayHasKey('output_buffer', $event);

        $this->assertInternalType('string', $event['title']);
        $this->assertInternalType('string', $event['output']);
        $this->assertInternalType('object', $event['exception']);
        $this->assertSame('foo bar', $event['output_buffer']);
    }

    private function doRender(CliErrorScreen $screen, \Throwable $exception, bool $debug, ?string $outputBuffer = 'foo bar'): string
    {
        $outputStream = fopen('php://memory', 'r+');

        $screen->setOutputStream($outputStream);

        $this->assertSame($outputStream, $screen->getOutputStream());

        $screen->render($exception, $debug, $outputBuffer);

        $output = stream_get_contents($outputStream, -1, 0);
        fclose($outputStream);

        return $output;
    }
}
