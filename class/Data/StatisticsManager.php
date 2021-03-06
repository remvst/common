<?php
namespace common\Data;

use common\Exception\HttpException as HttpException;

abstract class StatisticsManager{
	const COL_IP = 'IP';
	const COL_TIMESTAMP = 'TIMESTAMP';
	const COL_USERAGENT = 'USERAGENT';
	const COL_LANG = 'LANG';
	
	protected $allowedInterval; // interval between statistics to be sent (seconds)
	protected $checkDuplicates; // Specifies whether duplicate statistics should be allowed.
	protected $paramName; // The name of the parameter which will be sent
	protected $statisticsID; // Identifies the type of manager (to differentiate session vars)
	protected $allowRawData;
	
	public function __construct(){
		$this->allowedInterval = 0;
		$this->checkDuplicates = true;
		$this->paramName = 'params';
		$this->statisticsID = get_class($this);
		$this->allowRawData = false;
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
		if($this->allowRawData && isset($params['mode']) && $params['mode'] == 'raw'){
			$stats = urldecode($params[$this->paramName]);
		}else{
        	$stats = \common\Util\Decoder::decode($params[$this->paramName]);
		}
        
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
                throw new \Exception('Missing ' . $required_columns[$i] . ' (data: ' . \common\Util\Decoder::decode($params[$this->paramName]) . ')');
            }
        }

        // Applying censorship
        $censored_columns = $this->getCensoredColumns();
        foreach($censored_columns as $c){
        	$stats[$c] = $this->censorString($stats[$c]);
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
				case self::COL_IP: $content = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''; break;
				case self::COL_TIMESTAMP: $content = time(); break;
				case self::COL_USERAGENT: $content = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''; break;
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
		$qb->select('COUNT(*)','nb_inf');
		
		if($this->getSortOrder() == 'DESC'){
			$qb->where($sortedColumn . '>=:value');
		}else{
			$qb->where($sortedColumn . '<=:value');
		}
		$qb->setParam('value',$ref);
			
		$this->addGameSpecificWhereClause($qb,$entity);
		
		$res = DB::fetch($qb->getQuery());
		return $res[0]['nb_inf'];
	}
	
	/**
	 * Adds specific where clause to entities that require it.
	 */
	public function addGameSpecificWhereClause($qb,$entity){
		
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

	/**
	 * Censoring a string
	 */
	public function censorString($s){
		$f = fopen(RESOURCE_FOLDER . '/misc/censorship.txt','r');
		while($l = trim(fgets($f))){
			if(!empty($l) && stripos($s,$l) !== false){
				$stars = $l[0];
				for($i = 1 ; $i < strlen($l) ; $i++){
					$stars .= '-';
				}

				$s = preg_replace('/\b' . $l . '\b/i', $stars, $s);
			}
		}
		fclose($f);

		return $s;
	}

	protected function getCensoredColumns(){
		return array();
	}
}
