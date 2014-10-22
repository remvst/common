<?php
namespace common\Engine;
 
/**
 * IniConfiguration creates a new configuration based on
 * a .ini file.
 */
class IniConfiguration{
	private $file;
	private $content;
	private $values;
	
	/**
	 * Creates a new configuration. If a file is specified,
	 * the file is parsed.
	 * @param $file The file to read.
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
	 * @param $key The parameter name.
	 * @return The parameter if set, null otherwise.
	 */
	public function getValue($key,$default = null){
		return isset($this->values[$key]) ? $this->values[$key] : $default;
	}
	
	/**
	 * Setting a value for the specified key.
	 * @param $key The parameter name.
	 * @param $value The value to set.
	 */
	public function setValue($key,$value){
		$this->values[$key] = $value;
	}
	
	/**
	 * Fusing with another IniConfiguration object.
	 * This basically adds the other configuration's content
	 * to the current one.
	 * @param otherFile The configuration file to use.
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
	 * @param $dir The directory to build.
	 * @return The resulting IniConfiguration object.
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
