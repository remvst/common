<?php
namespace common\Data;

/**
 * Class to load SQL files.
 */
class QueryLoader extends Query{
	private $query;
	
	/**
	 * Creates a new QueryLoader from the specified file.
	 * @param $file The file to read.
	 */
	public function __construct($file){
		parent::__construct();
		$this->query = file_get_contents($file);
	}
	
	public function getQuery(){
		$q = $this->query;
		foreach($this->params as $param=>$value){
			$q = str_replace('%'.$param.'%',$value,$q);
		}
		return $q;
	}
}
