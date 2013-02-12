<?php
namespace common\Engine;

use \common\Data\DB as DB;
use \common\Engine\Application as Application;

class Response{
	private $content;
	private $headers;
	
	public function __construct(){
		$this->content = '';
		$this->headers = array();
	}
	
	public function addHeader($header){
		$this->headers[] = $header;
	}
	
	public function getContent(){
		return $this->content;
	}
	
	public function setContent($content){
		$this->content = $content;
	}
	
	public function render(){
		// Setting headers
		foreach($this->headers as $header){
			header($header);
		}
		
		// Adding development info
		if(ENV_LOCAL || Application::getRunningApplication()->debugMode()){
			$bodyPos = strrpos($this->content,'</body>');
			if($bodyPos !== false){
				try{
					$route = Application::getRunningApplication()->getRouter()->getRoute(Application::getRunningApplication()->getRequest());
					$routeStr = $route['controller'] . '->' . $route['action'];
				}catch(\Exception $e){
					// Checking the 
					$routeStr = 'None found';
				}
				
				$exec = microtime(true) -  $_SERVER['REQUEST_TIME_FLOAT'];
				$infos = array(
					'Execution' => round($exec * 1000) . 'ms',
					'Queries' => DB::getQueries() . ' (' . round(DB::getQueryTime(),5) . ' - ' . round(100 * DB::getQueryTime() / $exec,1) . '%)',
					'Route' => $routeStr,
					'Request' => Application::getRunningApplication()->getRequest()->getRequestedPath()
				);
				
				$id = Application::getRunningApplication()->getIdentity();
				if(is_object($id)){
					$infos['Identity'] = $id->getName() . ' (' . implode(',',$id->getPermissionsArray()) . ')';
				}else{
					$infos['Identity'] = 'none';
				}
				
				$toolbar = '<div style="position:fixed;width:100%;border-top:1px solid gray;bottom:0px;left:0px;background-color: silver; box-shadow: 0px 0px 10px silver; ">';
				foreach($infos as $info=>$value){
					$toolbar .= '<span style="height: 20px; overflow: auto; padding:5px;display:block;float:left;width:200px;border-right:1px solid gray;font-size:0.8em;">' . $info . ': ' . $value . '</span>';
				}
				$toolbar .= '</div>';
				
				$this->content = substr($this->content,0,$bodyPos) . $toolbar . substr($this->content,$bodyPos);
			}
		}
		
		// Showing content
		echo $this->content;
	}
}
