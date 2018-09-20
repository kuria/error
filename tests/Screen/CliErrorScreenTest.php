<?php declare(strict_types=1);

namespace Kuria\Error\Screen;

use Kuria\DevMeta\Test;

class CliErrorScreenTest extends Test
{
    function testShouldRender()
    {
        $screen = new CliErrorScreen();

        $output = $this->doRender($screen, $this->createTestException(), false);

        $this->assertContains('An error has occured', $output);
        $this->assertContains('Test exception', $output);
        $this->assertNotContains('Test previous exception', $output);
        $this->assertNotContains('{main}', $output);
    }

    function testShouldEmitRenderEvent()
    {
        $handlerCalled = false;

        $screen = new CliErrorScreen();

        $screen->on(CliErrorScreenEvents::RENDER, function ($event) use (&$handlerCalled) {
            $this->assertFalse($handlerCalled);
            $this->assertRenderEvent($event);

            $event['title'] .= ' lorem ipsum';
            $event['output'] .= "\n\nDolor sit amet";

            $handlerCalled = true;
        });

        $output = $this->doRender($screen, $this->createTestException(), false);

        $this->assertTrue($handlerCalled);
        $this->assertContains('An error has occured lorem ipsum', $output);
        $this->assertContains('Test exception', $output);
        $this->assertContains('Dolor sit amet', $output);
        $this->assertNotContains('Test previous exception', $output);
        $this->assertNotContains('{main}', $output);
    }

    function testShouldReplaceDefaultOutputViaRenderEvent()
    {
        $handlerCalled = false;

        $screen = new CliErrorScreen();

        $screen->on(CliErrorScreenEvents::RENDER, function ($event) use (&$handlerCalled) {
            $this->assertFalse($handlerCalled);
            $this->assertRenderEvent($event);

            $event['title'] = 'Lorem ipsum';
            $event['output'] = 'Dolor sit amet';

            $handlerCalled = true;
        });

        $output = $this->doRender($screen, $this->createTestException(), false);

        $this->assertTrue($handlerCalled);
        $this->assertContains('Lorem ipsum', $output);
        $this->assertContains('Dolor sit amet', $output);
        $this->assertNotContains('An error has occured', $output);
        $this->assertNotContains('Test exception', $output);
        $this->assertNotContains('Test previous exception', $output);
        $this->assertNotContains('{main}', $output);
    }

    function testShouldRenderInDebugMode()
    {
        $screen = new CliErrorScreen();

        $output = $this->doRender($screen, $this->createTestException(), true);

        $this->assertContains('An error has occured', $output);
        $this->assertContains('Test exception', $output);
    }

    function testShouldEmitRenderEventInDebugMode()
    {
        $handlerCalled = false;

        $screen = new CliErrorScreen();

        $screen->on(CliErrorScreenEvents::RENDER_DEBUG, function ($event) use (&$handlerCalled) {
            $this->assertFalse($handlerCalled);
            $this->assertRenderEvent($event);

            $event['title'] .= ' lorem ipsum';
            $event['output'] .= "\n\nDolor sit amet";

            $handlerCalled = true;
        });

        $output = $this->doRender($screen, $this->createTestException(), true);

        $this->assertTrue($handlerCalled);
        $this->assertContains('An error has occured lorem ipsum', $output);
        $this->assertContains('Test exception', $output);
        $this->assertContains('Test previous exception', $output);
        $this->assertContains('Dolor sit amet', $output);
        $this->assertContains('{main}', $output);
    }

    function testShouldReplaceDefaultOutputViaRenderEventInDebugMode()
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

        $output = $this->doRender($screen, $this->createTestException(), true);

        $this->assertTrue($handlerCalled);
        $this->assertContains('Lorem ipsum', $output);
        $this->assertContains('Dolor sit amet', $output);
        $this->assertNotContains('An error has occured', $output);
        $this->assertNotContains('Test exception', $output);
        $this->assertNotContains('Test previous exception', $output);
        $this->assertNotContains('{main}', $output);
    }

    private function assertRenderEvent($event): void
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

    private function createTestException(): \Throwable
    {
        return new \Exception('Test exception', 0, new \Exception('Test previous exception'));
    }
}
