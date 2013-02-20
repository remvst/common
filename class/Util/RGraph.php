<?php
namespace common\Util;

/**
 * Class for easy RGraph management.
 */
class RGraph{
	private static $nextId = 1;
	private static $included = array();
	
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
		
		$this->required = array();
		$this->data = array();
		$this->params = array();
		
		$this->width = 400;
		$this->height = 400;
		
		$this->libFolder = '';
		
		// Some default settings
		$this->set('chart.shadow', true);
		$this->set('chart.title', $title);
		$this->set('chart.linewidth', 3);
		$this->set('chart.gutter.left', 40);
		$this->set('chart.gutter.right', 40);
		$this->set('chart.gutter.top', 40);
		$this->set('chart.gutter.bottom', 40);
		$this->set('chart.text.size', 8);
		$this->set('chart.text.angle', 45);
		
		// Adding default requirements
		$this->requires('common.core');
		$this->requires(strtolower($this->type));
	}
	
	/**
	 * Specifying the data to plot.
	 * @param $data The data.
	 */
	public function setData($data){
		if(is_array($data)){
			$this->data = array(json_encode($data,JSON_NUMERIC_CHECK));
		}
	}
	
	/**
	 * Adding a data set.
	 * @param $set The data set.
	 */
	public function addDataSet($set){
		if(is_array($set)){
			$this->data[] = json_encode($set,JSON_NUMERIC_CHECK);
		}
	}
	
	/**
	 * Setting a parameter.
	 * @param $name The parameter name.
	 * @param $value The value to set.
	 */
	public function set($name,$value){
		if(is_string($value)){
			$value = '"' . $value . '"';
		}else{
			$value = json_encode($value,JSON_NUMERIC_CHECK);
		}
		$this->params[$name] = $value;
	}
	
	/**
	 * Setting the chart's labels.
	 * @param $labels The array of labels.
	 */
	public function setLabels($labels){
		if(is_array($labels)){
			$this->set('chart.labels',$labels);	
		}
	}
	
	/**
	 * Getting the chart's title.
	 * @return The title.
	 */
	public function getTitle(){
		return $this->title;
	}
	
	/**
	 * Getting the chart type.
	 * @return The type of chart.
	 */
	public function getType(){
		return $this->type;
	}
	
	/**
	 * Getting the chart's HTML render.
	 * @return The HTML code.
	 */
	public function render(){
		$s = "<canvas id='chart-" . $this->id . "' width='" . $this->width . "' height='" . $this->height . "'></canvas>\n\n";
		
		foreach($this->required as $r){
			if(!in_array($r,self::$included)){
				$s .= "<script src='" . $this->libFolder . "/RGraph." . $r . ".js'></script>\n";
				self::$included[] = $r;
			}
		}
		
		$s .= "<script>\n";
		$s .= "var chart" . $this->id . " = new RGraph." . ucwords($this->type) . "('chart-" . $this->id . "', " . implode(',',$this->data) . ");\n";
		foreach($this->params as $p=>$v){
			$s .= "chart" . $this->id . ".Set('" . $p . "'," . $v . ");\n";
		}
		$s .= "chart" . $this->id . ".Draw();\n";
		$s .= "</script>";
		
		return $s;
	}

	/**
	 * Setting the path to the RGraph js files.
	 * @param $folder 
	 */
	public function setLibFolder($folder){
		$this->libFolder = $folder;
	}
	
	/**
	 * Setting the chart width.
	 * @param $width
	 */
	public function setWidth($width){
		$this->width = $width;
	}
	
	/**
	 * Setting the chart height.
	 * @param $height 
	 */
	public function setHeight($height){
		$this->height = $height;
	}
	
	/**
	 * Add a required RGraph library.
	 * @param $lib The library name (without the RGraph. prefix and the extension)
	 */
	public function requires($lib){
		$this->required[] = $lib;
	}
}