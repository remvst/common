<?php
namespace common\Data;

/**
 * Class for database interaction.
 * This class does not provide any security support. For that, you will
 * need to use higher-level classes such as Repository.
 * 
 * Works only with MySQL.
 * 
 * Each instance represents a different connection to the database.
 * If all your applications use the same database, you can use the
 * static methods.
 */
class DB{
	private $pdo = null;
	private $queries = array();
	private $queryTime = 0;
	
	/**
	 * Static array to store all database connections.
	 */
	private static $connections = array();

	/**
	 * Building a new connection to a database.
	 * @param $params Array of parameters : host,user,pass,name,port 
	 */
	public function __construct($params){
		try{
			$this->pdo = new \PDO('mysql:host='.$params['host'].';port='.$params['port'].';dbname='.$params['name'], $params['user'], $params['pass']);
		}catch(PDOException $e){
			throw new \common\Exception\DatabaseException('Database connection failure.');
		}
		
		// Setting everything to UTF-8
		$this->execute("SET NAMES 'utf8'");
		
		// Adding the connection to the connections list
		self::$connections[] = $this;
	}
	
	/**
	 * Executing a query with no results.
	 * @throws DatabaseException if any problem while executing the query.
	 * @param $query The SQL query to execute.
	 * @return The number of affected rows.
	 */
	public function execute($query){
		$this->queries[] = $query;
		
		$start = microtime(true);
		$changes = $this->pdo->exec($query);
		if($changes === false){
			throw new \common\Exception\DatabaseException('Error while executing query : ' . implode(' - ',$this->pdo->errorInfo()) . '.',$query);
		}
		$this->queryTime += microtime(true) - $start;
		
		return $changes;
	}

	/**
	 * Executing a query and getting the results.
	 * @throws DatabaseException if any problem while executing the query.
	 * @param $query The SQL query to execute.
	 * @return The results.
	 */
	public function fetchResults($query){
		$this->queries[] = $query;
		
		$start = microtime(true);
		
		// Executing the query
		$res = $this->pdo->query($query);
		if($res === false){
			throw new \common\Exception\DatabaseException('Error while executing query : ' . implode(' - ',$this->pdo->errorInfo()) . '.',$query);
		}
		
		// Then returning an array with all results
		$return = array();
		$res->setFetchMode(\PDO::FETCH_ASSOC);
		while($l = $res->fetch()){
			$return[] = $l;
		}
		$res->closeCursor();
		$this->queryTime += microtime(true) - $start;
		
		return $return;
	}
	
	/**
	 * @param $string The string to be quoted.
	 * @return The quoted string.
	 */
	public function quoteString($string){
		return $this->pdo->quote($string);
	}

	/**
	 * Getting the main connection : the connection
	 * that was opened by the currently running app.
	 * @return The main connection.
	 */
	private static function getMainConnection(){
		return \common\Engine\Application::getRunningApplication()->getDB();
	}
	
	/**
	 * Executing a query with no expected result.
	 * Uses the running application's connection.
	 * @param $query The query to execute.
	 * @return The affected rows.
	 */
	public static function exec($query){
		return self::getMainConnection()->execute($query);
	}
	
	/**
	 * Getting data from the database
	 * Uses the running application's connection.
	 * @param $query The query to execute.
	 * @return The result.
	 */
	public static function fetch($query){
		return self::getMainConnection()->fetchResults($query);
	}
	
	/**
	 * Quoting a string in order to use it in a query
	 * @param $string The string to quote.
	 * @return The quoted string.
	 */
	public static function quote($string){
		return self::getMainConnection()->quoteString($string);
	}
	
	/**
	 * Getting the last inserted ID.
	 */
	public function getInsertId(){
		return $this->pdo->lastInsertId();
	}
	
	public static function insertId(){
		return self::getMainConnection()->getInsertId();
	}
	
	/**
	 * Getting the queries that have been executed.
	 * @return The array of queries which have been executed.
	 */
	public function getQueries(){
		return $this->queries;
	}

	/**
	 * Getting the time spent to proceed with the queries.
	 * @return The time spent on queries.
	 */
	public function getQueryTime(){
		return $this->queryTime;
	}
	
	/**
	 * Getting all connections.
	 */
	public static function getConnections(){
		return self::$connections;
	}
	
	public function close(){
		$this->db = null;
	}
}
