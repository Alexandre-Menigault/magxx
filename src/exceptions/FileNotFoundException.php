<?php

class FileNotFoundException extends Exception
{
    const NOT_FOUND_MESSAGE  = "File not found";

    public function __construct($filepath)
    {

        parent::__construct(FileNotFoundException::NOT_FOUND_MESSAGE . ": " . $filepath);
    }
}
