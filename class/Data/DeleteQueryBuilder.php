<?php
namespace common\Data;

class DeleteQueryBuilder extends QueryBuilder{
	private $table;
	private $where;

	public function __construct($table){
		$this->table = $table;
		$this->where = array();
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
		// Adding an AND operator if needed.
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
		$sql = 'DELETE FROM ' . $this->table . ' ';
		
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
			
			// Issue with backslashes
			$value = str_replace('\\','\\\\',$value);
			
			//$sql = str_replace(':'.$param,$value,$sql);
			$sql = preg_replace('#:'.$param.'([^a-zA-Z0-9]|$)#',$value.'$1',$sql);
		}
		
		return $sql;
	}
}