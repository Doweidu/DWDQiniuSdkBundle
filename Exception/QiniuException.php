<?php

namespace DWD\QiniuSdkBundle\Exception;

class QiniuException extends \Exception
{
    protected $key;

    public function __construct( $message, $code = 0, $key = null )
    {
        $this->key = $key;
        parent::__construct( $message, $code );
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: key:\"{$this->key}\" {$this->message}";
    }
}
