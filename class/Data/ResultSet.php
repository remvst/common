<?php
/**
 * Not used.
 */
namespace common\Data;

class ResultSet implements \ArrayAccess{
	
	private $pdoResult;
	private $storedResults;
	
	public function __construct($pdoResult){
		$this->pdoResult = $pdoResult;
		$this->pdoResult->setFetchMode(\PDO::FETCH_ASSOC);
		
		$this->storedResults = array();
	}
	
	public function offsetExists($offset){
		if(!is_numeric($offset)){
			return false;
		}else{
			return ($offset < $pdoResult->rowCount());
		}
	}
	
	public function offsetGet($offset){
		if(!$this->offsetExists($offset)){
			throw new \Exception('Offset does not exist.');
		}
		
		$this->fillResults($offset);
		return $this->storedResults[$offset];
	}
	
	public function offsetSet($offset,$value){
		throw new \Exception('Result sets are read-only.');
	}
	
	public function offsetUnset($offse){
		throw new \Exception('Result sets are read-only.');
	}
	
	private function fillResults($offset){
		echo 'Fill with ' . $offset;
		while(count($this->storedResults) < $offset + 1){
			$this->storedResults[] = $this->pdoResult->fetch();
		}
		if($offset == $this->pdoResult->rowCount() - 1){
			$this->pdoResult->closeCursor();
		}
	}
}