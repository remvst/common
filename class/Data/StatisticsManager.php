<?php
namespace common\Data;

use common\Exception\HttpException as HttpException;

abstract class StatisticsManager{
	const COL_IP = 1;
	const COL_TIMESTAMP = 2;
	const COL_USERAGENT = 3;
	const COL_LANG = 4;
	
	protected $allowedInterval; // interval between statistics to be sent (seconds)
	protected $checkDuplicates; // Specifies whether duplicate statistics should be allowed.
	protected $paramName;
	protected $statisticsID;
	
	public function __construct(){
		$this->allowedInterval = 0;
		$this->checkDuplicates = true;
		$this->paramName = 'params';
		$this->statisticsID = get_class($this);
	}
	
	/**
	 * Processes the given request to save statistics.
	 */
	public function process(\common\Engine\Application $app,\common\Engine\Request $request){
		// Getting raw params
		$params = $request->getParameters();
		if(!isset($params[$this->paramName])){
			$app->getResponse()->addHeader('HTTP/1.0 400 Bad Request');
			$app->addLog('Error: no parameters.'); 
			throw new HttpException(400,'Missing parameters.');
		}
		
		// Checking time since last stat
		$last_time = $app->getSessionVar('last_time_' . $this->statisticsID);
		$elapsed = $_SERVER['REQUEST_TIME'] - $last_time;
		if($elapsed < $this->allowedInterval){
            $app->getResponse()->addHeader('HTTP/1.0 400 Bad request');
			$app->addLog('Error: interval of ' . $elapsed . ' s only'); 
			throw new HttpException(400,'Too fast.');
		}else{
            $app->setSessionVar('last_time_' . $this->statisticsID,time());
		}
		
		// Decoding the param string
        $stats = \common\Util\Decoder::decode($params[$this->paramName]);
        
		// Checking if this is the same request as the previous one
        $last_stats = $app->getSessionVar('last_stats_'.$this->statisticsID);
        if($last_stats !== null && $last_stats == $stats && $this->checkDuplicates){
            $app->getResponse()->addHeader('HTTP/1.0 400 Bad request');
            throw new HttpException(400,'Duplicate data.');
        }else{
            $app->setSessionVar('last_stats_'.$this->statisticsID,$stats);
        }
        
        // Parsing the raw string
        $stats = \common\Util\Decoder::parse($stats);
		
		// Checking if columns are all included
		$required_columns = $this->getSentColumns();
		$nCols = count($required_columns);
        for($i = 0 ; $i < $nCols ; ++$i){
            if(!isset($stats[$required_columns[$i]])){
                $app->getResponse()->addHeader("HTTP/1.0 400 Bad Request");
                $app->addLog('Incorrect data received : ' . utf8_encode(\common\Util\Decoder::decode($params[$this->paramName]))); 
                throw new HttpException(400,'Missing ' . $required_columns[$i] . ' (data: ' . \common\Util\Decoder::decode($params[$this->paramName]) . ')');
            }
        }
		
		// Adding server-generated columns
		$this->addAutomaticColumns($stats);
		
		// Creating the object to persist
		$entity = $this->createEntity($stats);
		
		// Persisting the entity
		$this->save($entity);
		
		return $entity;
	}
	
	/**
	 * Adds automatic columns.
	 */
	protected function addAutomaticColumns(&$stats){
		$columns = $this->getAutomaticColumns();
		foreach($columns as $c=>$type){
			$content = null;
			switch($type){
				case self::COL_IP: $content = $_SERVER['REMOTE_ADDR']; break;
				case self::COL_TIMESTAMP: $content = time(); break;
				case self::COL_USERAGENT: $content = $_SERVER['HTTP_USER_AGENT']; break;
				case self::COL_LANG: $content = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : ''; break;
			}
			if($content === null){
				throw new \Exception('Couldn\'t generate column type : ' . $type);
			}
			$stats[$c] = $content;
		}
	}
	
	/**
	 * Creates the entity to persist.
	 */
	private function createEntity($columns){
		$type = $this->getEntityType();
		$entity = new $type($columns);
		return $entity;
	}
	
	/**
	 * Persists the given entity.
	 */
	private function save($entity){
		$repository = $this->getRepository();
		$repository->persist($entity);
		$repository->flush();
	}
	
	/**
	 * Retrieves statistics from the repository.
	 */
	public function getStats($columns,$offset,$number,$sortedColumn = null,$where = null){
		if($sortedColumn === null){
			$sortedColumn = array($this->getSortedColumn() => $this->getSortOrder());
		}
		
		$repo = $this->getRepository();
		$res = $repo->find($columns,$where,$sortedColumn,$offset,$number);
		return $res;
	}
	
	/**
	 * Gets the game's rank.
	 */
	public function getRank($entity){
		$sortedColumn = $this->getSortedColumn();
		$method = 'get' . ucwords($sortedColumn);
		$ref = $entity->$method();
		
		$table = $this->getRepository()->getTable();
		$qb = new QueryBuilder($table,'t');
		$qb->select('COUNT(*)','nb_inf')
			->where($sortedColumn . '>=:value')
			->setParam('value',$ref);
		
		$res = DB::fetch($qb->getQuery());
		return $res[0]['nb_inf'];
	}
	
	/**
	 * Gets the repository to use
	 */
	public function getRepository(){
		return \common\Data\Repository::getRepository($this->getEntityType());
	}
	
	/**
	 * Getting the daily stats for the game.
	 * @return The array of date and games.
	 */
	public function getDailyStats(){
		$repo = $this->getRepository();
		
		$qb = new QueryBuilder($repo->getTable(),'t');
		$qb
			->select('COUNT(*)','games')
			->select('DATE(FROM_UNIXTIME(' . $this->getTimestampColumn() . '))','date')
			->groupBy('DATE(FROM_UNIXTIME(' . $this->getTimestampColumn() . '))')
			->orderBy('DATE(FROM_UNIXTIME(' . $this->getTimestampColumn() . '))','DESC')
			->setMaxResults(60);
			
		$res = DB::fetch($qb->getQuery());
		
		return $res;
	}
	
	/**
	 * Gets the column corresponding to the timestamp of the date, which
	 * will be used for the daily statistics.
	 */
	public function getTimestampColumn(){
		return 'date';
	}
	
	/**
	 * Gets the columns sent by the client
	 */
	public abstract function getSentColumns();
	
	/**
	 * Gets columns generated by the server (date, IP, user agent...)
	 */
	public abstract function getAutomaticColumns();
	
	/**
	 * Gets the column to sort by.
	 */
	public abstract function getSortedColumn();
	
	/**
	 * Gets the order to sort by.
	 */
	public abstract function getSortOrder();
	
	/**
	 * Gets the type of object to persist.
	 */
	public abstract function getEntityType();
}
