<?php
namespace common\Data;
abstract class Query{
	protected $params;
	
	public function __construct(){
		$this->params = array();
	}
	
	/**
	 * Sets the specified parameter.
	 */
	public function setParam($name,$value){
		$this->params[$name] = $value;
		return $this;
	}
	
	/**
	 * Gets the query string to execute.
	 */
	public abstract function getQuery();
}
