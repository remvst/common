<?php
namespace common\Data;
class QueryLoader extends Query{
	private $query;
	
	public function __construct($file){
		parent::__construct();
		$this->query = file_get_contents($file);
	}
	
	public function getQuery(){
		$q = $this->query;
		foreach($this->params as $param=>$value){
			$q = str_replace('%'.$param.'%',$value,$q);
		}
		//~ echo $q;
		return $q;
	}
}
