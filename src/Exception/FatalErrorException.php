<?php declare(strict_types=1);

namespace Kuria\Error\Exception;

/**
 * Used to describe fatal errors which terminate the script
 */
class FatalErrorException extends \ErrorException implements ExceptionInterface
{
}
