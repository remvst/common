<?php
namespace common\Util;

class CacheManifest{
	private $modified;
	private $files;
	private $extensions;
	private $root;
	
	public function __construct(){
		$this->modified = 0;
		$this->extensions = array(
			'js','html','css',
			'png','jpg','jpeg',
			'ttf'
		);
		$this->files = array(
			'CACHE' => array(),
			'NETWORK' => array(),
			'FALLBACK' => array()
		);
		$this->root = '';
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
			$this->files[$type][] = $file;
			
			// Modification
			if(file_exists($file)){
				$modified = filemtime($file);
				$this->modified = max($this->modified,$modified);
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
		$d = opendir($dir);
		while($f = readdir($d)){
			$path = $dir . '/' . $f;
			if($f != '.' && $f != '..'){
				if(is_dir($path)){
					$this->addDirectory($path);
				}else{
					$this->addFile($path);
				}
			}
		}
		closedir($d);
	}
	
	/**
	 * Rendering the manifest.
	 * @param $reponse The reponse to apply the content-type header to.
	 */
	public function render($response = null){
		if($response !== null){
			$response->addHeader('Content-type: text/cache-manifest');
		}
		
		$s = "CACHE MANIFEST\n\n";
		$s .= "# Automatically generated\n";
		$s .= '# Last modified  ' . date('Y-m-d H:i:s',$this->modified) . "\n";
		foreach(array('CACHE','NETWORK','FALLBACK') as $type){
			if(count($this->files[$type]) > 0){
				$s .= "\n" . $type . ":\n";
				
				foreach($this->files[$type] as $f){
					// Removing the document root
					if(strpos($f,$_SERVER['DOCUMENT_ROOT']) === 0){
						$f = substr($f,strlen($_SERVER['DOCUMENT_ROOT']));
					}
					
					$s .= $f . "\n";
				}
			}
		}
		
		return $s;
	}
}