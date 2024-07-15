<?php

namespace App\Exceptions\Sellsy;

use Exception;

class ExceptionResult extends Exception
{
    protected $data;

    public function __construct(string $message = "", array $data = [])
    {
        parent::__construct($message);
        $this->data = $data;
    }

    public function getData(): array
    {
        return $this->data;
    }
}