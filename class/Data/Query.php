<?php
namespace common\Data;

/**
 * Abstract class for all SQL queries.
 */
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
