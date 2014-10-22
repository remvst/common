<?php
namespace common\Exception;

/**
 * Class for all Authentication-related exceptions.
 */
class AuthenticationException extends \common\Exception\HttpException{
	public function __construct($message){
		parent::__construct(400,$message);
	}
}
