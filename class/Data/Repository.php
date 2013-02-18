<?php
namespace common\Data;

/**
 * Repository : enables easier interactions with the Database for 
 * specific entity types.
 */
abstract class Repository{
	private static $repositories = array();
	
	private $entityType;
	private $transactionStarted;
	private $db;
	
	/**
	 * Creates a new repository. The reason why this
	 * constructor is private is to avoid multiple 
	 * instances for the same repository.
	 */
	private function __construct($db){
		$this->entityType = str_replace('Repository','',get_class($this));
		$this->transactionStarted = false;
		$this->db = $db;
	}
	
	/**
	 * Getting the table columns.
	 * @return The array of objectAttribute => sqlColumn
	 */
	protected abstract function getColumns();
	
	/**
	 * Getting the primary key.
	 * @return The array of attributes which define the primary key.
	 */
	protected abstract function getKey();
	
	/**
	 * Getting the table name.
	 * @return The table name in the database.
	 */
	public abstract function getTable();
	
	/**
	 * Finding all data.
	 * @return All data from the repository.
	 */
	public function findAll(){
		return $this->find();
	}
	
	/**
	 * Allowing findByX methods.
	 */
	public function __call($method_name,$method_params){
		if(strpos($method_name,'findBy') == 0){
			$col = strtolower(str_replace('findBy','',$method_name));
			
			$columns = $this->getColumns();
			if(!isset($columns[$col])){
				throw new \Exception('Column ' . $col . ' doesn\'t exist.');
			}
			return $this->find(null,array($col=>$method_params[0]));
		}
	}
	
	/**
	 * Finding data from the repository.
	 * The $where parameters specifies AND conditions.
	 * @param $columns The columns to retrieve.
	 * @param $where The where clauses (will be linked by AND)
	 * @param $orderBy The ways to order by.
	 * @param $firstResult The offset.
	 * @param $maxResults The maximum number of results to retrieve.
	 * @return The results.
	 */
	public function find($columns = null,$where = null,$orderBy = null,$firstResult = 0,$maxResults = null){
		$entityColumns = $this->getColumns();
		
		$qb = new QueryBuilder($this->getTable(),'t');
		
		// If no column has been specified, then we get all of them.
		if($columns === null){
			$columns = array_keys($entityColumns);
		}
		
		// SELECT clause
		foreach($columns as $col){
			$qb->select($this->formatColumn($col,'t'),$col);
		}
		
		// WHERE clause
		if($where !== null && count($where) > 0){
			$i = 0;
			foreach($where as $col=>$value){
				// Adding the AND link between conditions.
				if($i > 0)	$qb->andWhere();
				
				// Adding the condition, using a parameter.
				$qb->where($this->formatColumn($col,'t').'=:param'.$i);
				++$i;
			}
			
			// Then replacing parameters.
			$i = 0;
			foreach($where as $col=>$value){
				$qb->setParam('param'.$i,$value);
				++$i;
			}
		}
		
		// Adding the ORDER BY clause
		if($orderBy !== null){
			foreach($orderBy as $col=>$way){
				$qb->orderBy($this->formatColumn($col,'t'),$way);
			}
		}
		
		// Setting the LIMIT clause.
		$qb->setFirstResult($firstResult);
		$qb->setMaxResults($maxResults);
		
		$res = $this->db->fetchResults($qb->getQuery());
            
        // Finally, creating objects with the actual type
        $objects = array();
        foreach($res as $line){
            $obj = $this->createObject($line); // works only if the constructor is not overridden                
            $objects[] = $obj;                
        }
        return $objects;
	}
	
	/**
	 * Getting a repository from the entity type.
	 * @param $entityType The type of entity to be handled via the repository.
	 * @return The repository. 
	 */
	public static function getRepository($entityType,DB $db = null){
		// Creates the repository if not created yet.
		if(!isset(self::$repositories[$entityType])){
			$className = $entityType . 'Repository';
			
			// If no database is specified, using the default one.
			if($db === null){
				$db = \common\Engine\Application::getRunningApplication()->getDB();
			}
				
			self::$repositories[$entityType] = new $className($db);
		}
		
		return self::$repositories[$entityType];
	}
	
	/**
	 * Adding a new entity to save into the database in the current transaction.
	 * @param $entity The entity to persist.
	 */
	public function persist($entity){
		// Starting a transaction if not started yet.
		if(!$this->transactionStarted){
			$this->transactionStarted = true;
			$this->db->execute('START TRANSACTION');
		}
		
		$entityColumns = $this->getColumns();
		
		// Creating a query builder.
        if($entity->isNew()){
        	$qb = new InsertQueryBuilder($this->getTable());
        }else{
        	$qb = new UpdateQueryBuilder($this->getTable());
			
			// If the query is an update, we have to had a WHERE clause.
			foreach($this->getKey() as $key){
                $getter = 'get'.ucwords($key);
                
				$qb->where($entityColumns[$key] . '=:whereparam' . $key);
				$qb->setParam('whereparam' . $key,$entity->$getter());
            }
        }
		
		// Adding the values for each attribute.
        foreach($entityColumns as $objCol=>$sqlCol){
            $getter = 'get'.ucwords($objCol);
			$value = $entity->$getter();
			
			// Adding the attribute to the query only if not null.
			if($value != null){
        		$qb->addColumn($sqlCol,':valueparam'.$objCol);
				$qb->setParam('valueparam' . $objCol,$entity->$getter());
			}
        }
		
		$query = $qb->getQuery();
		
        if($this->db->execute($query) == 1){
        	// Updating the entity ID if newly created.
			if($entity->isNew()){
            	$entity->setId($this->db->insertId());	
			}
			return true;
        }else{
        	return false;
        }
	} 
	
	/**
	 * Commits the current transaction.
	 */
	public function flush(){
		$this->transactionStarted = false;
		$this->db->execute('COMMIT');
	}
	
	/**
	 * Creates an object based on the specified data.
	 * @param $data The array representing the entity. 
	 * @return The created object.
	 */
	protected function createObject($data){
		$obj = new $this->entityType($data);
		$obj->setExists(true);
		return $obj;
	}
	
	/**
	 * Gets columns that are calculated via SQL.
	 * @return An array of alias => column function.
	 */
	protected function getDynamicColumns(){
		return null;
	}
	
	/**
	 * Gets the SQL column corresponding to the given alias.
	 * @param $columnName The column name.
	 * @return The column's name or function in the database.
	 */
	private function seekColumn($columnName){
		$cols = $this->getColumns();
		if(isset($cols[$columnName])){
			return $cols[$columnName];
		}
		
		$cols = $this->getDynamicColumns();
		if(isset($cols[$columnName])){
			return $cols[$columnName];
		}
		
		return null;
	}
	
	/**
	 * Formats the specified column for the specified 
	 * table prefix. This is especially useful for 
	 * dynamic columns, for which the prefix might 
	 * not be at the same location.
	 * @param $columnName
	 * @param $prefix
	 * @return The formatted column.
	 */
	private function formatColumn($columnName,$prefix){
		// Checking in the regular columns.
		$cols = $this->getColumns();
		if(isset($cols[$columnName])){
			return $prefix . '.' . $cols[$columnName];
		}
		
		// Checking in the dynamic columns.
		$cols = $this->getDynamicColumns();
		if(isset($cols[$columnName])){
			return str_replace('%prefix%',$prefix,$cols[$columnName]);
		}
		
		throw new \Exception('Column ' . $columnName . ' doesn\'t exist');
	}
	
	public function dump(){
		if(!ENV_LOCAL){
			throw new \common\Exception\HttpException(400,'Local env only.');
		}
		
		$data = $this->findAll();
		
		$entityColumns = $this->getColumns();
		
		$query = '';
		
		// Creating the query builder.
		$qb = new InsertQueryBuilder($this->getTable());
		foreach($entityColumns as $entityCol=>$sqlCol){
			$qb->addColumn($sqlCol,':param'.$entityCol);
		}
		
		// For each row, setting parameters.
		foreach($data as $l){
			foreach($entityColumns as $entityCol=>$sqlCol){
				$getter = 'get' . ucwords($entityCol);
				$qb->setParam('param'.$entityCol,$l->$getter());
			}
			
			$query .= $qb->getQuery() . ";\n";
		}
		
		return $query;
	}
}