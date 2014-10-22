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
	 * Quoting the specified string.
	 * @param $str The string to quote.
	 * @return The quoted string.
	 */
	protected function quote($str){
		return DB::quote($str);
	}
	
	/**
	 * Gets the query string to execute.
	 */
	public abstract function getQuery();
}
