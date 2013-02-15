<?php
namespace common\Data;

/**
 * Class for database interaction.
 * This class does not provide any security support. For that, you will
 * need to use higher-level classes such as Repository.
 */
class DB{
	private static $pdo = null;
	private static $queries = array();
	private static $queryTime = 0;

	/**
	 * Connecting to the database if needed.
	 * At the moment, only one connection can be made at a time (can't 
	 * use several databases at the same time).
	 */
	public static function connect(){
		if(self::$pdo === null){
			try{
				$cfg = \common\Engine\Application::getRunningApplication()->getConfiguration();
				self::$pdo = new \PDO('mysql:host='.$cfg->getValue('dbhost').';port='.$cfg->getValue('dbport').';dbname='.$cfg->getValue('dbname'), $cfg->getValue('dbuser'), $cfg->getValue('dbpass'));
			}
			catch(PDOException $e){
				throw new \common\Exception\DatabaseException('Database connection failure.');
			}
			self::exec("SET NAMES 'utf8'");
		}
	}
	
	/**
	 * Executing a query with no expected result
	 */
	public static function exec($query){
		self::connect();
		
		self::$queries[] = $query;
		
		$start = microtime(true);
		$changes = self::$pdo->exec($query);
		if($changes === false){
			throw new \common\Exception\DatabaseException('Error while executing query : ' . implode(' - ',self::$pdo->errorInfo()) . '.',$query);
		}
		self::$queryTime += microtime(true) - $start;
		
		return $changes;
	}
	
	/**
	 * Getting data from the database
	 */
	public static function fetch($query){
		self::connect();
		
		self::$queries[] = $query;
		
		$start = microtime(true);
		
		// Executing the query
		$res = self::$pdo->query($query);
		if($res === false){
			throw new \common\Exception\DatabaseException('Error while executing query : ' . implode(' - ',self::$pdo->errorInfo()) . '.',$query);
		}
		
		// Then returning an array with all results
		$return = array();
		$res->setFetchMode(\PDO::FETCH_ASSOC);
		while($l = $res->fetch()){
			$return[] = $l;
		}
		$res->closeCursor();
		self::$queryTime += microtime(true) - $start;
		
		return $return;
	}
	
	/**
	 * Quoting a string in order to use it in a query
	 */
	public static function quote($string){
		self::connect();
		return self::$pdo->quote($string);
	}
	
	/**
	 * Getting the last inserted ID.
	 */
	public static function insertId(){
		self::connect();
		return self::$pdo->lastInsertId();
	}
	
	/**
	 * Getting the queries that have been executed.
	 */
	public static function getQueries(){
		return self::$queries;
	}

	/**
	 * Getting the time spent to proceed with the queries.
	 */
	public static function getQueryTime(){
		return self::$queryTime;
	}
}
