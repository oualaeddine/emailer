<?php

namespace App\Support\Network;

use RuntimeException;

class UnsafeUrlException extends RuntimeException
{
    public const INVALID = 'invalid';
    public const UNRESOLVED = 'unresolved';
    public const PRIVATE = 'private';

    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
