<?php
namespace common\Exception;

/**
 * Class for all database-related issues.
 */
class DatabaseException extends \Exception{
    public function __construct($message,$query = null){
        $this->message = $message . ($query !== null ? ' Query : ' . $query : '');
    }
}
