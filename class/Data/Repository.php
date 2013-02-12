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
	
	/**
	 * 
	 */
	private function __construct(){
		$this->entityType = str_replace('Repository','',get_class($this));
		$this->transactionStarted = false;
	}
	
	/**
	 * Getting the table columns.
	 */
	protected abstract function getColumns();
	
	/**
	 * Getting the primary key.
	 */
	protected abstract function getKey();
	
	/**
	 * Getting the table name.
	 */
	public abstract function getTable();
	
	/**
	 * Finding all data.
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
	 */
	public function find($columns = null,$where = null,$orderBy = null,$firstResult = 0,$maxResults = null){
		$entityColumns = $this->getColumns();
		
		$qb = new QueryBuilder($this->getTable(),'t');
		
		// If no column has been specified, then we get all of them. 
		$sep = '';
		if($columns === null){
			foreach($entityColumns as $col=>$sqlCol){
				$qb->select('t.'.$sqlCol,$col);
			}
		}else{
			foreach($columns as $col){
				$qb->select($this->formatColumn($col,'t'),$col);
			}
		}
		
		if($where !== null && count($where) > 0){
			$i = 0;
			$sep = '';
			$whereClause = '';
			foreach($where as $col=>$value){
				$whereClause .= $sep.$this->formatColumn($col,'t').'=:param'.$i;
				$sep = ' AND ';
				++$i;
			}
			
			$qb->where($whereClause);
			
			$i = 0;
			foreach($where as $col=>$value){
				$qb->setParam('param'.$i,$value);
				++$i;
			}
		}
		
		if($orderBy !== null){
			foreach($orderBy as $col=>$way){
				$qb->orderBy($this->formatColumn($col,'t'),$way);
			}
		}
		
		$qb->setFirstResult($firstResult);
		$qb->setMaxResults($maxResults);
		
		//~ echo $qb->getQuery();
		
		$res = DB::fetch($qb->getQuery());
            
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
	 */
	public static function getRepository($repositoryName){
		if(!isset(self::$repositories[$repositoryName])){
			$className = $repositoryName . 'Repository';
			self::$repositories[$repositoryName] = new $className();
		}
		return self::$repositories[$repositoryName];
	}
	
	/**
	 * Adding a new entity to save into the database in the current transaction.
	 */
	public function persist($entity){
		// Starting a transaction if needed.
		if(!$this->transactionStarted){
			$this->transactionStarted = true;
			DB::exec('START TRANSACTION');
		}
		
		$entityColumns = $this->getColumns();
		
        if($entity->isNew()){
            // Creating a new line for the table
            $sqlValues = '';
            $sep = '';
            $sqlColumns = '';
            foreach($entityColumns as $objCol=>$sqlCol){
                $sqlColumns .= $sep.$sqlCol;
                
                $getter = 'get'.ucwords($objCol);
                $sqlValues .= $sep . DB::quote($entity->$getter());
                
                $sep = ', ';
            }
            
            $query = 'INSERT INTO '.$this->getTable().' (' . $sqlColumns . ') VALUES (' . $sqlValues . ')';
            
            $res = (DB::exec($query) == 1);
            
            $entity->setId(DB::insertId());
            
            return $res;
        }else{
            // Updating the line
            $sqlValues = '';
            $sep = '';
            foreach($entityColumns as $objCol=>$sqlCol){
                $getter = 'get'.ucwords($objCol);
                $sqlValues .= $sep . $sqlCol . ' = ' . DB::quote($entity->$getter());
                $sep = ', ';
            }
            
            // WHERE clause : quite important, since it will allow only the right
            // line to be changed.
            $where = '';
            $sep = '';
            foreach($this->getKey() as $key){
                $getter = 'get'.ucwords($key);
                
                $where .= $sep . $entityColumns[$key] . ' = ' . DB::quote($entity->$getter());
                $sep = ' AND '; 
            }
            
            $query = 'UPDATE ' . $this->getTable() . ' 
            SET ' . $sqlValues . ' 
            WHERE ' . $where;
            
            return (DB::exec($query) == 1);
        }
	} 
	
	/**
	 * Commits the current transaction.
	 */
	public function flush(){
		$this->transactionStarted = false;
		DB::exec('COMMIT');
	}
	
	/**
	 * Creates an object based on the specified data.
	 */
	protected function createObject($data){
		$obj = new $this->entityType($data);
		$obj->setExists(true);
		return $obj;
	}
	
	/**
	 * Gets columns that are calculated via SQL.
	 */
	protected function getDynamicColumns(){
		return null;
	}
	
	/**
	 * Gets the SQL column corresponding to the given alias.
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
	
	private function formatColumn($columnName,$prefix){
		$col = $this->seekColumn($columnName);
		
		$cols = $this->getColumns();
		if(isset($cols[$columnName])){
			return $prefix . '.' . $cols[$columnName];
		}
		
		$cols = $this->getDynamicColumns();
		if(isset($cols[$columnName])){
			return str_replace('%prefix%',$prefix,$cols[$columnName]);
		}
		
		throw new \Exception('Column ' . $columnName . ' doesn\'t exist');
	}
}
