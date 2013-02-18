<?php
namespace common\Data;

class UpdateQueryBuilder extends Query{
	private $table;
	private $columns;
	private $where;
	
	public function __construct($table){
		parent::__construct();
		$this->table = $table;
		$this->columns = array();
		$this->where = array();
	}
	
	public function addColumn($column,$value){
		// Storing empty parameters.
		if($value[0] == ':'){
			$this->params[substr($value,1)] = null;
		}
		
		$this->columns[$column] = $value;
		
		return $this;
	}
	
	public function andWhere(){
		$this->where[] = ' AND ';
		return $this;
	}
	
	public function orWhere(){
		$this->where[] = ' OR ';
		return $this;
	}
	
	public function where($condition){
		// Adding an OR operator if needed.
		if(count($this->where) > 0){
			$last = $this->where[count($this->where)-1];
			if($last !== ' AND ' && $last !== ' OR '){
				$this->andWhere();
			}
		}
		
		$this->where[] = $condition;
		
		// Detecting parameters
		preg_match_all('#:([a-zA-Z0-9_-]+)#',$condition,$matches);
		foreach($matches[0] as $match){
			$this->params[substr($match,1)] = null;
		}
		
		return $this;
	}
	
	public function getQuery(){
		$sql = 'UPDATE ' . $this->table . ' SET ';
		
		// Adding new values.
		$sep = '';
		foreach($this->columns as $column=>$value){
			if($value instanceof Query)
				$value = $value->getQuery();
			
			$sql .= $sep . $column . '=' . $value;
			$sep = ',';
		}
		
		// Adding the WHERE clause
		if(count($this->where) > 0){
			$sql .= ' WHERE ' . implode('',$this->where);
		}
		
		// Replacing parameters
		foreach($this->params as $param=>$value){
			if($value === null){
				throw new \common\Exception\DatabaseException('Parameter "' . $param . '" has no value.');	
			}
			
			if($value instanceof Query)
				$value = $value->getQuery();
			
			//$sql = str_replace(':'.$param,$value,$sql);
			$sql = preg_replace('#:'.$param.'([^a-zA-Z0-9]|$)#',$value.'$1',$sql);
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
