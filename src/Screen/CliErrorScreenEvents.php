<?php declare(strict_types=1);

namespace Kuria\Error\Screen;

/**
 * @see CliErrorScreen
 */
abstract class CliErrorScreenEvents
{
    /**
     * @param array $view
     */
    const RENDER = 'render';

    /**
     * @param array $view
     */
    const RENDER_DEBUG = 'render.debug';
}
