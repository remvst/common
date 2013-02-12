<?php
/**
 * Exception for common HTTP errors (404...)
 */
namespace common\Exception;
class HttpException extends \Exception{
	private $errorCode;

	private static $messages = array(
		400 => 'Bad request',
		404 => 'Not found',
		500 => 'Server error'
	);
	
	public function __construct($error_code,$message = null){
		parent::__construct($message);
		
		$this->errorCode = $error_code;
		
		if($message == null){
			if(isset(self::$messages[$error_code])){
				$this->message = self::$messages[$error_code];
			}else{
				$this->message = 'Unknown error.';
			}
		}
	}
	
	public function apply($response){
		$response->addHeader('HTTP/1.1 ' . $this->errorCode . ' ' . $this->message);
	}
}
