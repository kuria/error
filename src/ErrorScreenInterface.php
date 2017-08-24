<?php declare(strict_types=1);

namespace Kuria\Error;

interface ErrorScreenInterface
{
    function render(\Throwable $exception, bool $debug, ?string $outputBuffer = null);
}
