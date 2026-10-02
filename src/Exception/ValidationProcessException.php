<?php

namespace App\Exception;

/**
 * Error raised while processing a validation whose message is safe to be returned to the user
 * (no path, no command line...). Other errors are reported with a generic message.
 */
class ValidationProcessException extends \RuntimeException
{
}
