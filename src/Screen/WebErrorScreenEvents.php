<?php declare(strict_types=1);

namespace Kuria\Error\Screen;

/**
 * @see WebErrorScreen
 */
abstract class WebErrorScreenEvents
{
    /**
     * Emitted when rendering an exception.
     *
     * @param array $view
     */
    const RENDER = 'render';

    /**
     * Emitted when rendering an exception in debug mode.
     *
     * @param array $view
     */
    const RENDER_DEBUG = 'render.debug';

    /**
     * Emitted when layout's CSS content is being rendered.
     *
     * @param array $view
     */
    const LAYOUT_CSS = 'layout.css';

    /**
     * Emitted when layout's JS content is being rendered.
     *
     * @param array $view
     */
    const LAYOUT_JS = 'layout.js';
}
