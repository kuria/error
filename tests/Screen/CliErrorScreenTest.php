<?php

namespace Kuria\Error\Screen;

class CliErrorScreenTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param CliErrorScreen $screen
     * @param object         $exception
     * @param bool           $debug
     * @param string|null    $outputBuffer
     * @return string
     */
    private function doRender(CliErrorScreen $screen, $exception, $debug, $outputBuffer = 'foo bar')
    {
        $outputStream = fopen('php://memory', 'r+');

        $screen->setOutputStream($outputStream);

        $this->assertSame($outputStream, $screen->getOutputStream());

        $screen->handle($exception, $debug, $outputBuffer);

        $output = stream_get_contents($outputStream, -1, 0);
        fclose($outputStream);

        return $output;
    }

    public function testRender()
    {
        $screen = new CliErrorScreen();

        $output = $this->doRender($screen, new \Exception('Test exception'), false);

        $this->assertContains('An error has occured', $output);
        $this->assertContains('Enable debug mode for more details', $output);
        $this->assertNotContains('Test exception', $output);
    }

    public function testRenderEvent()
    {
        $that = $this;
        $handlerCalled = false;

        $screen = new CliErrorScreen();

        $screen->on('render', function (&$view, $exception, $outputBuffer, $screen) use ($that, &$handlerCalled) {
            $that->assertFalse($handlerCalled);
            $that->assertEventArguments($view, $exception, $outputBuffer, $screen);

            $view['title'] = 'Lorem ipsum';
            $view['output'] .= "\n\nDolor sit amet";

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

    public function testRenderEventCustomOutput()
    {
        $that = $this;
        $handlerCalled = false;

        $screen = new CliErrorScreen();

        $screen->on('render', function (&$view, $exception, $outputBuffer, $screen) use ($that, &$handlerCalled) {
            $that->assertFalse($handlerCalled);
            $that->assertEventArguments($view, $exception, $outputBuffer, $screen);

            $view['title'] = 'Lorem ipsum';
            $view['output'] = 'Dolor sit amet';

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

    public function testDebugRender()
    {
        $screen = new CliErrorScreen();

        $output = $this->doRender($screen, new \Exception('Test exception'), true);

        $this->assertContains('An error has occured', $output);
        $this->assertContains('Test exception', $output);
    }

    public function testDebugRenderEvent()
    {
        $that = $this;
        $handlerCalled = false;

        $screen = new CliErrorScreen();

        $screen->on('render.debug', function (&$view, $exception, $outputBuffer, $screen) use ($that, &$handlerCalled) {
            $that->assertFalse($handlerCalled);
            $that->assertEventArguments($view, $exception, $outputBuffer, $screen);

            $view['title'] = 'Lorem ipsum';
            $view['output'] .= "\n\nDolor sit amet";

            $handlerCalled = true;
        });

        $output = $this->doRender($screen, new \Exception('Test exception'), true);

        $this->assertTrue($handlerCalled);
        $this->assertNotContains('An error has occured', $output);
        $this->assertContains('Lorem ipsum', $output);
        $this->assertContains('Test exception', $output);
        $this->assertContains('Dolor sit amet', $output);
    }

    public function testDebugRenderEventCustomOutput()
    {
        $that = $this;
        $handlerCalled = false;

        $screen = new CliErrorScreen();

        $screen->on('render.debug', function (&$view, $exception, $outputBuffer, $screen) use ($that, &$handlerCalled) {
            $that->assertFalse($handlerCalled);
            $that->assertEventArguments($view, $exception, $outputBuffer, $screen);

            $view['title'] = 'Lorem ipsum';
            $view['output'] = 'Dolor sit amet';

            $handlerCalled = true;
        });

        $output = $this->doRender($screen, new \Exception('Test exception'), true);

        $this->assertTrue($handlerCalled);
        $this->assertNotContains('An error has occured', $output);
        $this->assertNotContains('Test exception', $output);
        $this->assertContains('Lorem ipsum', $output);
        $this->assertContains('Dolor sit amet', $output);
    }

    public function assertEventArguments($view, $exception, $outputBuffer, $screen)
    {
        $this->assertInternalType('array', $view);
        $this->assertArrayHasKey('title', $view);
        $this->assertArrayHasKey('output', $view);
        $this->assertInternalType('object', $exception);
        $this->assertSame('foo bar', $outputBuffer);
        $this->assertInstanceOf(__NAMESPACE__ . '\CliErrorScreen', $screen);
    }
}
