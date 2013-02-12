<?php
/**
 * 
 */
namespace common\Engine;
 
class IniConfiguration{
	private $file;
	private $content;
	private $values;
	
	/**
	 * 
	 */
	public function __construct($file = null){
		$this->values = array();
		$this->content = '';
		
		if($file !== null){
			if(!file_exists($file)){
				throw new \InvalidArgumentException('File ' . $file . ' does not exist');
			}
			
			$this->file = $file;
			$this->content = file_get_contents($this->file);
			
			$lines = explode("\n",$this->content);
			foreach($lines as $line){
				$line = trim($line);
				if(strlen($line) > 0 && $line[0] != ';'){
					$split = explode('=',$line);
					if(count($split) == 2){
						$this->values[$split[0]] = $split[1];
					}
				}
			}
		}
	}
	
	/**
	 * Getting a value. If the value doesn't exist, null is returned
	 * instead.
	 */
	public function getValue($key){
		return isset($this->values[$key]) ? $this->values[$key] : null;
	}
	
	/**
	 * Setting a value for the specified key.
	 */
	public function setValue($key,$value){
		$this->values[$key] = $value;
	}
	
	/**
	 * Fusing with another IniParser object.
	 */
	public function fuse(IniConfiguration $otherFile){
		foreach($otherFile->values as $key=>$val){
			$this->values[$key] = $val;
		}
	}
	
	/**
	 * Building configuration from a directory.
	 * Loads config.ini, and then config-dev.ini or config-prod.ini, depending  
	 * on the environment.
	 * If one of the files doesn't exist, the function does not throw any
	 * Exception.
	 */
	public static function buildDirectory($dir){
		$files = array($dir.'/config.ini');
		if(ENV_LOCAL){
			$files[] = $dir.'/config-dev.ini';
		}else{
			$files[] = $dir.'/config-prod.ini';
		}
		
		$cfg = new IniConfiguration();
		foreach($files as $f){
			if(file_exists($f)){
				try{
					$cfg->fuse(new IniConfiguration($f));
				}catch(Exception $e){}
			}
		}
		return $cfg;
	}
}
