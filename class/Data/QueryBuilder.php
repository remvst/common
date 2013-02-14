<?php 
namespace common\Data;

/**
 * Class which allows the dynamic creation of SQL queries.
 * This class also provides parameter usage, for security purpose.
 * Works only for MySQL.
 */
class QueryBuilder extends Query{
	private $tables;
	private $columns;
	private $where;
	private $orderBy;
	private $firstResult;
	private $maxResults;
	private $links;
	
	/**
	 * Creates a new Query for the specified table.
	 * @param $table The table to use.
	 * @param $alias The table's alias.
	 */
	public function __construct($table = null,$alias = 't'){
		parent::__construct();
		
		// Initializing query params.
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
	 * @param $table
	 * @param $alias
	 * @return The current QueryBuilder object.
	 */
	public function from($table,$alias){
		$this->tables[] = array('table'=>$table,'alias'=>$alias);
		return $this;
	}
	
	/**
	 * Adds a union clause
	 * @return The current QueryBuilder object.
	 */
	public function union(Query $query){
		$this->links[] = array(
			'type' => 'UNION',
			'query' => $query
		);
		return $this;
	}
	
	/**
	 * Adds a minus clause
	 * @return The current QueryBuilder object.
	 */
	public function minus(Query $query){
		$this->links[] = array(
			'type' => 'MINUS',
			'query' => $query
		);
		return $this;
	}
	
	/**
	 * Adding a new column to retrieve.
	 * @return The current QueryBuilder object.
	 */
	public function select($column,$alias){
		if(strlen($this->columns) > 0){
			$this->columns .= ',';
		}
		$this->columns .= $column . ' ' . $alias;
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
	
	/**
	 * Adding a new condition to the WHERE clause.
	 * The condition has to be a string, so if you
	 * wish to use subqueries for conditions, you 
	 * should use parameters.
	 * @param $condition The condition to add.
	 * @return The current QueryBuilder object.
	 */
	public function where($condition){
		// Adding an OR operator if needed.
		if(count($this->where) > 0){
			$last = $this->where[count($this->where)-1];
			if($last !== ' AND ' && $last !== ' OR '){
				$this->andWhere();
			}
		}
		
		// Adding the actual condition
		$this->where[] = $condition;

		// Storing parameters names with a default null value.
		preg_match_all('#:([a-zA-Z0-9_-]+)#',$condition,$matches);
		foreach($matches[0] as $match){
			$this->params[substr($match,1)] = null;
		}
		return $this;
	}
	
	/**
	 * Adding a new ORDER BY column.
	 * @param $column The column to order by.
	 * @param $way The way to order by (DESC or ASC)
	 * @return The current QueryBuilder object.
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
	 * @param $first The first result to get (offset).
	 * @return The current QueryBuilder object.
	 */
	public function setFirstResult($first){
		$this->firstResult = (int)$first;
		return $this;
	}
	
	/**
	 * Setting the maximum number of results.
	 * @param $max The number of results.
	 * @return The current QueryBuilder object.
	 */
	public function setMaxResults($max){
		$this->maxResults = (int)$max;
		return $this;
	}
	
	/**
	 * Getting the actual SQL query.
	 * @return The query string.
	 */
	public function getQuery(){
		$query = 'SELECT ' . $this->columns;
		
		// FROM clause
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
		
		// WHERE clause
		if(count($this->where) > 0){
			$where = ' WHERE ';
			foreach($this->where as $condition){
				$where .= $condition;
			}
			
			// Replacing parameters. They were already quoted
			// in the setParam method.
			foreach($this->params as $param=>$value){
				if($value === null){
					throw new \common\Exception\DatabaseException('Parameter ' . $param . ' has no value.');
				}else if($value instanceof Query){
					$value = $value->getQuery();
				}
				
				$where = str_replace(':'.$param,$value,$where);
			}
			
			$query .= $where;
		}
		
		// ORDER BY clause
		if(strlen($this->orderBy) > 0){
			$query .= ' ORDER BY ' . $this->orderBy;
		}
		
		// Links (UNION, MINUS...)
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
	
	/**
	 * Sets the specified param to the specified value.
	 * @throws DatabaseException if the parameter does not exist in any of the WHERE clauses.
	 * @param $name
	 * @param $value
	 * @return The current QueryBuilder object.
	 */
	public function setParam($name,$value){
		if(!array_key_exists($name,$this->params)){
			throw new \common\Exception\DatabaseException('Parameter ' . $name . ' does not exist.');
		}
		
		// If the parameter is a string (and not a 
		// Query object), we quote it.
		if(is_string($value)){
			$value = $this->quote($value);
		}
		return parent::setParam($name,$value);
	}
}
