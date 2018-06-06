<?php

namespace App\Services;


use Throwable;

class ServiceException extends \Exception
{
    protected $context;

    public function __construct($message = "", $context = [], $code = 0, Throwable $previous = null)
    {
        $this->context = $context;

        parent::__construct($message, $code, $previous);
    }

    public function getContext()
    {
        return $this->context;
    }

    public function getMessageEx()
    {
        return implode(' ', array_filter([$this->message, json_encode($this->context)]));
    }
}