<?php 
namespace common\Data;
class QueryBuilder extends Query{
	private $tables;
	private $columns;
	private $where;
	private $orderBy;
	private $firstResult;
	private $maxResults;
	private $links;
	
	public function __construct($table = null,$alias = 't'){
		parent::__construct();
		
		$this->table = $table;
		$this->tableAlias = $alias;
		
		$this->columns = '';
		$this->orderBy = '';
		$this->where = array();
		$this->firstResult = 0;
		$this->maxResults = null;
		$this->links = array();
		
		$this->tables = array();
		if($table !== null){
			$this->from($table,$alias);
		}
	}
	
	/**
	 * Adds a table/view to the FROM clause
	 */
	public function from($table,$alias){
		$this->tables[] = array('table'=>$table,'alias'=>$alias);
	}
	
	/**
	 * Adds a union clause
	 */
	public function union(Query $query){
		$this->links[] = array(
			'type' => 'UNION',
			'query' => $query
		);
	}
	
	/**
	 * Adds a minus clause
	 */
	public function minus(Query $query){
		$this->links[] = array(
			'type' => 'MINUS',
			'query' => $query
		);
	}
	
	/**
	 * Adding a new column to retrieve.
	 */
	public function select($column,$alias){
		if(strlen($this->columns) > 0){
			$this->columns .= ',';
		}
		$this->columns .= $column . ' ' . $alias;
		return $this;
	}
	
	public function andWhere(){
		$this->where[] = 'AND';
		return $this;
	}
	
	public function orWhere(){
		$this->where[] = 'OR';
		return $this;
	}
	
	/**
	 * Adding a new condition to the WHERE clause.
	 */
	public function where($left,$operator = null,$right = null){
		if(count($this->where) > 0){
			$last = $this->where[count($this->where)];
			if($last !== 'AND' && $last !== 'OR')
				$this->where[] = 'OR';
		}
		
		$this->where[] = $left;

		// Storing parameters
		preg_match_all('#:([a-zA-Z0-9_-]+)#',$left,$matches);
		foreach($matches[0] as $match){
			$this->params[substr($match,1)] = null;
		}
		return $this;
	}
	
	/**
	 * Adding a new ORDER BY column.
	 */
	public function orderBy($column,$way = 'ASC'){
		if(strlen($this->orderBy) > 0){
			$this->orderBy .= ',';
		}
		$this->orderBy .= $column . ' ' . $way;
		return $this;
	}
	
	/**
	 * Setting the first result.
	 */
	public function setFirstResult($first){
		$this->firstResult = (int)$first;
		return $this;
	}
	
	/**
	 * Setting the maximum number of results.
	 */
	public function setMaxResults($max){
		$this->maxResults = (int)$max;
		return $this;
	}
	
	/**
	 * Getting the actual SQL query.
	 */
	public function getQuery(){
		$query = 'SELECT ' . $this->columns;
		
		$query .= ' FROM ';
		$sep = '';
		foreach($this->tables as $table){
			if($table['table'] instanceof Query){
				$query .= $sep . $table['table']->getQuery() . ' AS ' . $table['alias'];
			}else{
				$query .= $sep . $table['table'] . ' ' . $table['alias'];
			}
			$sep = ',';
		}
		
		if(count($this->where) > 0){
			$where = ' WHERE ';
			foreach($this->where as $condition){
				if($where instanceof Query){
					$where .= $condition->getQuery();
				}else{
					$where .= $condition;
				}
			}
			
			foreach($this->params as $param=>$value){
				$where = str_replace(':'.$param,$value,$where);
			}
			
			$query .= $where;
		}
		
		// ORDER BY clause
		if(strlen($this->orderBy) > 0){
			$query .= ' ORDER BY ' . $this->orderBy;
		}
		
		// Links (union, minus...)
		foreach($this->links as $link){
			$query .= ' ' . $link['type'] . ' ' . $link['query']->getQuery();
		}
		
		// LIMIT clause
		if($this->maxResults != null || $this->firstResult > 0){
			$query .= ' LIMIT ' . $this->firstResult;
			if($this->maxResults != null){
				$query .= ','.$this->maxResults;
			}
		}
		return $query;
	}
	
	public function setParam($name,$value){
		if(!array_key_exists($name,$this->params)){
			throw new \Exception('Parameter ' . $name . ' does not exist.');
		}
		if(is_string($value)){
			$value = $this->quote($value);
		}
		return parent::setParam($name,$value);
	}
	
	protected function quote($str){
		return DB::quote($str);
	}
}
