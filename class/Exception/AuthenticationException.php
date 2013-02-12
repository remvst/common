<?php
namespace common\Exception;

class AuthenticationException extends \common\Exception\HttpException{
	public function __construct($message){
		parent::__construct(400,$message);
	}
}
