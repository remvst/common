<?php
namespace common\Data;

class InsertQueryBuilder extends Query{
	private $columns;
	private $table;
	
	public function __construct($table){
		parent::__construct();
		$this->columns = array();
		$this->table = $table;
	}
	
	public function addColumn($column,$value){
		if($value[0] == ':'){
			$this->params[substr($value,1)] = null;
		}
		
		$this->columns[$column] = $value;
		
		return $this;
	}
	
	public function getQuery(){
		$sql = 'INSERT INTO ' . $this->table . ' ';
		$sql .= '('.implode(',',array_keys($this->columns)).') VALUES ';
		$sql .= '('.implode(',',array_values($this->columns)).')';
		
		foreach($this->params as $param=>$value){
			//echo $param . ' <br />';
			if($value === null){
				echo $param;
				//throw new \common\Exception\DatabaseException('Parameter "' . $param . '" has no value.');	
			}
			
			if($value instanceof Query)
				$value = $value->getQuery();
			
			$sql = str_replace(':'.$param,$value,$sql);
		}
		
		return $sql;
	}
	
	/**
	 * Sets the specified param to the specified value.
	 * @throws DatabaseException if the parameter does not exist in any of the WHERE clauses.
	 * @param $name
	 * @param $value
	 * @return The current QueryBuilder object.
	 */
	public function setParam($name,$value){
		// If the parameter is a string (and not a 
		// Query object), we quote it.
		if(is_string($value)){
			$value = $this->quote($value);
		}
		return parent::setParam($name,$value);
	}
}
