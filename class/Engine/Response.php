<?php
namespace common\Engine;

/**
 * Class representing the response that will be sent
 * to the client.
 */
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
		if(\common\Engine\Application::getRunningApplication()->debugMode()){
			$bodyPos = strrpos($this->content,'</body>');
			if($bodyPos !== false){
				try{
					$route = \common\Engine\Application::getRunningApplication()->getRouter()->getRoute(\common\Engine\Application::getRunningApplication()->getRequest());
					$routeStr = (isset($route['name']) ? $route['name'] . ': ' : '') . $route['controller'] . '::' . $route['action'] . 'Action()';
				}catch(\Exception $e){
					// Checking the 
					$routeStr = 'None found';
				}
				
				$exec = microtime(true) -  $_SERVER['REQUEST_TIME_FLOAT'];
				$infos = array(
					'Execution' => round($exec * 1000) . 'ms',
					'Route' => $routeStr,
					'Request' => Application::getRunningApplication()->getRequest()->getRequestedPath()
				);
				
				$dbConnections = \common\Data\DB::getConnections();
				$i = 1;
				foreach($dbConnections as $connection){
					$infos['DB #'.$i] = count($connection->getQueries()) . ' queries (' . round($connection->getQueryTime(),5) . ' - ' . round(100 * $connection->getQueryTime() / $exec,1) . '%)';
					++$i;
				}
				
				$id = \common\Engine\Application::getRunningApplication()->getIdentity();
				if(is_object($id)){
					$infos['Identity'] = $id->getName() . ' (' . implode(',',$id->getPermissionsArray()) . ')';
				}else{
					$infos['Identity'] = 'none';
				}
				
				$toolbar = '<br /><br /><div ontouchstart="this.style.display=\'none\'" onclick="this.style.display=\'none\'" style="position:fixed;width:100%;border-top:1px solid gray;bottom:0px;left:0px;background-color: silver; box-shadow: 0px 0px 10px black; ">';
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
