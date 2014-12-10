<?php
namespace common\Util;

class CacheManifest{
	//~ const CONSTANT = 'constant value';
	//~ const MAX_SIZE = ; //(5 * 1024 * 1024 * 1024);
	
	private $modified;
	private $files;
	private $extensions;
	private $root;
	private $filesPerSize;
	private $maxSize;
	private $base;
	
	public function __construct($base = '.'){
		$this->modified = 0;
		$this->base = $base;
		$this->extensions = array(
			'js','html','css',
			'png','jpg','jpeg',
			'ttf','otf',
			'ogg','mp3','wav'
		);
		$this->files = array(
			'CACHE' => array(),
			'NETWORK' => array(),
			'FALLBACK' => array()
		);
		$this->root = '';
		$this->filesPerSize = array();
		$this->maxSize = 5 * 1024 * 1024;
	}
	
	public function setMaxSize($maxSize){
		$this->maxSize = $maxSize;
	}

	public function getMaxSize(){
		return $this->maxSize;
	}


	
	/**
	 * Adding the specified file.
	 * @param $file The file to add.
	 * @param $type The type of file (NETWORK, FALLBACK, or CACHE)
	 */
	public function addFile($file,$type = 'CACHE'){
		$ext = substr($file,strrpos($file,'.') + 1);
		
		// Checking if the extensions is enabled
		if($file == '*' || in_array($ext, $this->extensions)){
			$add = array(
				'path' => $file
			);
			
			// Modification
			if(file_exists($this->base . '/' . $file)){
				$add['modified'] = filemtime($this->base . '/' . $file);
				$add['size'] = filesize($this->base . '/' . $file);
				$this->modified = max($this->modified,$add['modified']);
				
				$i = 0;
				while($i < count($this->files[$type]) && $add['size'] > $this->files[$type][$i]['size']){
					$i++;
				}
				array_splice($this->files[$type],$i,0,array($add));
			}else{
				$this->files[$type][] = $add;
			}
		}
	}
	
	/**
	 * Setting the extensions to enable.
	 * @param $extensions The array of extensions.
	 */
	public function setExtensions($extensions){
		$this->extensions = $extensions;
	}
	
	/**
	 * Adding a new directory.
	 * @param $dir The directory path.
	 */
	public function addDirectory($dir){
	    if(file_exists($this->base . '/' . $dir)){
    		$d = opendir($this->base . '/' . $dir);
    		while($f = readdir($d)){
    			$path = $dir . '/' . $f;
    			if($f != '.' && $f != '..'){
    				if(is_dir($this->base . '/' . $path)){
    					$this->addDirectory($path);
    				}else{
    					$this->addFile($path);
    				}
    			}
    		}
    		closedir($d);
        }
	}
	
	public function removeBigFiles($files,$maxSize){
		$res = array();
		
		$totalSize = 0;
		$i = 0;
		while($totalSize < $maxSize && $i < count($files)){
			if(isset($files[$i]['size'])){
				$totalSize += $files[$i]['size'];
			}
			$i++;
		}
		
		//~ echo 'Limited to ' . $totalSize / (1024 * 1024) . ' - ' . ($i-1) . '/' . count($files);
		
		$res = array_slice($files,0,$i-1);
		
		return $res;
	}
	
	/**
	 * Rendering the manifest.
	 * @param $reponse The reponse to apply the content-type header to.
	 */
	public function render($response = null){
		$this->files['CACHE'] = $this->removeBigFiles($this->files['CACHE'],$this->maxSize);
		//~ print_r($this->files);
		//~ $this->files['CACHE'] = $this->removeBigFiles($this->files['CACHE'],5*1024*1024);
		
		if($response !== null){
			$response->addHeader('Content-type: text/cache-manifest');
		}
		
		$s = "CACHE MANIFEST\n\n";
		$s .= "# Automatically generated manifest\n";
		$s .= '# Last modified  ' . date('Y-m-d H:i:s',$this->modified) . "\n";
		foreach($this->files as $type=>$files){
			if(count($files) > 0){
				$s .= "\n" . $type . ":\n";
				
				foreach($files as $f){
					$path = $f['path'];

					// Removing the document root
					if(strpos($path,$_SERVER['DOCUMENT_ROOT']) === 0){
						$path = substr($path,strlen($_SERVER['DOCUMENT_ROOT']));
					}
					
					$s .= $path . "\n";
				}
			}
		}
		
		return $s;
	}
}
