<?php

namespace App\Exceptions;

use RuntimeException;

class MediaInUseException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Slika se koristi i ne može se obrisati.');
    }
}
