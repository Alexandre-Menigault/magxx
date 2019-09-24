<?php

class CannotWriteOnFileException extends Exception
{
    const CANNOT_WRITE_MESSAGE  = "Cannot write on file";

    public function __construct($filepath, $extraMessage = "")
    {
        parent::__construct(CannotWriteOnFileException::CANNOT_WRITE_MESSAGE . ": " . $filepath . ". " . $extraMessage);
    }
}
