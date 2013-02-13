<?php
namespace common\Engine;
 
/**
 * Request class
 * Represents any request sent by the client.
 * All paths are relative to the application's root directory.
 */
class Request{
	private $params;
	private $postParams;
	private $getParams;
	
	private $requestedURI;
	private $requestedFile;
	private $requestedFiletype;
	private $requestedPath;
	
	/**
	 * Building a new Request object.
	 * Sets all member attributes using the global variables.
	 */
	public function __construct($app){
		$this->params = null;
		
		$this->requestedURI = $app->getPathFromRootDir(urldecode($_SERVER['REQUEST_URI']));
		
		$split = explode('?',$this->requestedURI);
		$this->requestedPath = $split[0];
		
		$split_file = explode('/',$split[0]);
		$this->requestedFile = end($split_file);
		
		$split_extension = explode('.',$this->requestedFile);
		$this->requestedFiletype = end($split_extension);
	}
	
	/**
	 * Getting parameters.
	 * $type defines the type of parameters (get or post), or both.
	 */
	public function getParameters($type = null){
		if($this->params == null){
			$this->params = array();
			$this->postParams = array();
			$this->getParams = array();
			
			foreach($_REQUEST as $param=>$value){
				$this->params[$param] = $value;
			}
			foreach($_POST as $param=>$value){
				$this->postParams[$param] = $value;
			}
			foreach($_GET as $param=>$value){
				$this->getParams[$param] = $value;
			}
		}
		
		switch(strtolower($type)){
			case 'get': return $this->getParams;
			case 'post':return $this->postParams;
			default:    return $this->params;
		}
	}
	
	/**
	 * Getting the raw requested URI.
	 */
	public function getRequestedURI(){
		return $this->requestedURI;
	}
	
	/**
	 * Getting the requested file, without its path.
	 */
	public function getRequestedFile(){
		return $this->requestedFile;
	}
	
	/**
	 * Getting the requested file extension.
	 */
	public function getRequestedFiletype(){
		return $this->requestedFiletype;
	}
	
	/**
	 * Getting the requested path, without the GET parameters.
	 */
	public function getRequestedPath(){
		return $this->requestedPath;
	}
}
