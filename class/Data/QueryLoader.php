<?php
namespace common\Data;

/**
 * Class to load SQL files.
 */
class QueryLoader extends Query{
	private $query;
	private $links;
	
	/**
	 * Creates a new QueryLoader from the specified file.
	 * @param $file The file to read.
	 */
	public function __construct($file){
		parent::__construct();
		$this->query = file_get_contents($file);
		$this->links = array();
	}
	
	public function getQuery(){
		$q = $this->query;
		foreach($this->params as $param=>$value){
			$q = str_replace('%'.$param.'%',$value,$q);
		}
		foreach($this->links as $l){
			if($l['query'] instanceof Query){
				$sub = $l['query']->getQuery();
			}else{
				$sub = $l['query'];
			}
			
			$q .= ' ' . $l['type'] . ' ' . $sub;
		}
		return $q;
	}
	
	public function union($q){
		$this->links[] = array(
			'type' => 'UNION',
			'query' => $q
		);
	}
	
	public function minus($q){
		$this->links[] = array(
			'type' => 'MINUS',
			'query' => $q
		);
	}
}
