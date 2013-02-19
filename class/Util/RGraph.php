<?php
namespace common\Util;

class RGraph{
	private static $nextId = 1;
	
	private $id;
	private $title;
	private $type;
	private $labels;
	private $data;
	private $params;
	private $libFolder;
	private $width;
	private $height;
	
	public function __construct($title,$type){
		$this->id = self::$nextId++;
		
		$this->title = $title;
		$this->type = $type;
		
		$this->data = json_encode(array());
		$this->params = array();
		
		$this->width = '400';
		$this->height = '400';
		
		$this->libFolder = '';
		
		$this->set('chart.shadow', true);
		$this->set('chart.title', $title);
            
		/* $this->set('chart.gutter.left', 80);
		$this->set('chart.gutter.right', 20);
		$this->set('chart.gutter.top', 20);
		$this->set('chart.gutter.bottom', 80);*/
		
		$this->set('chart.linewidth', 3);
		$this->set('chart.gutter.left', 40);
		$this->set('chart.gutter.right', 40);
		$this->set('chart.gutter.top', 40);
		$this->set('chart.gutter.bottom', 40);
		
		$this->set('chart.text.size', 8);
		$this->set('chart.text.angle', 45);
	}
	
	public function setData($data){
		if(is_array($data)){
			$this->data = json_encode($data,JSON_NUMERIC_CHECK);
		}
	}
	
	public function set($name,$value){
		if(is_string($value)){
			$value = '"' . $value . '"';
		}else{
			$value = json_encode($value,JSON_NUMERIC_CHECK);
		}
		$this->params[$name] = $value;
	}
	
	public function setLabels($labels){
		if(is_array($labels)){
			$this->set('chart.labels',$labels);	
		}
	}
	
	public function getTitle(){
		return $this->title;
	}
	
	public function getType(){
		return $this->type;
	}
	
	public function getJsonLabels(){
		return json_encode($this->labels);
	}
	
	public function getJsonData(){
		return json_encode($this->data);
	}
	
	public function render(){
		$s = "<canvas id='chart-" . $this->id . "' width='" . $this->width . "' height='" . $this->height . "'></canvas>\n\n";
		
		$s .= "<script src='" . $this->libFolder . "/libraries/RGraph.common.core.js'></script>\n";
		$s .= "<script src='" . $this->libFolder . "/libraries/RGraph." . strtolower($this->type) . ".js'></script>\n";
		$s .= "<script>";
		$s .= "var chart" . $this->id . " = new RGraph." . ucwords($this->type) . "('chart-" . $this->id . "', " . $this->data . ");\n";
		foreach($this->params as $p=>$v){
			$s .= "chart" . $this->id . ".Set('" . $p . "'," . $v . ");\n";
		}
		$s .= "chart" . $this->id . ".Draw();";
		$s .= "</script>";
		
		return $s;
	}

	public function setLibFolder($folder){
		$this->libFolder = $folder;
	}
	
	public function setWidth($width){
		$this->width = $width;
	}
	
	public function setHeight($height){
		$this->height = $height;
	}
}
