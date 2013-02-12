<?php
namespace common\Exception;
class DatabaseException extends \Exception{
    public function __construct($message,$query = null){
        $this->message = $message . ($query !== null ? ' Query : ' . $query : '');
    }
}
