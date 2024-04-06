<?php

namespace App\Exceptions;

use Exception;

class UserException extends Exception
{
    /**
     * create by sandeep bara
     */
    public function __construct($errors)
    {
        parent::__construct($errors);
    }
}
