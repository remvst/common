<?php
/**
 * Application : main class for any website.
 * Then create both of these classes which should inherit from Router and 
 * Controller.
 */
namespace common\Engine;

use \Exception as Exception;

abstract class Application{
	
	private static $runningApplication = null;
	
	private $name;
	
	private $configuration;
	private $router;
	private $controller;
	private $response;
	private $request;
	private $rootPath;
	private $instantiated;
	private $resourceManager;
	
	private $identity;
	private $authManager;
	
	private $twig;
	
	/**
	 * Building the application : creates both the router and the controller,
	 * and executes the right action.
	 */
	public function __construct($cfg = null){
		// Getting application name from the class name
		$split_name = explode('\\',get_class($this));
		$this->name = str_replace('Application','',end($split_name));
		
		// Configuring autoloader for application-specific classes
		$appName = $this->name;
		spl_autoload_register(function($class){
			$classFile = APPS_FOLDER . '/' . str_replace('\\','/',$class) . '.php';
			if(file_exists($classFile)){
				require $classFile;
			}
		});
		
		// Adding application-specific configuration
		$this->configuration = $cfg;
		$this->configuration->fuse(IniConfiguration::buildDirectory($this->getConfigFolder()));
		
		// Initializing several variables.
		$this->twig = null;
		$this->authManager = null;
		$this->identity = null;
		
		//~ debug_print_backtrace();
		
	}
	
	/**
	 * Getting Twig.
	 */
	public function getTwig(){
		if($this->twig === null){
			\Twig_Autoloader::register();

			// Creating the cache directory if needed
			$cacheDir = COMMON_ROOT . '/cache/'.$this->getName().'/twig';
			if(!file_exists($cacheDir) && !mkdir($cacheDir,0755,true)){
				throw new \Exception('Unable to create cache directory : ' . $cacheDir);
			}
			
			$loader = new \Twig_Loader_Filesystem($this->getViewFolder());
			
			$params = array(
				'cache' => $cacheDir,
				'auto_reload' => ENV_LOCAL,
				'debug' => ENV_LOCAL
			);
			
			// 
			$this->twig = new \Twig_Environment($loader,$params);
			
			// Adding generateUrl() and asset() functions.
			$app = $this;
			$rootPath = $this->rootPath;
			$this->twig->addFunction(new \Twig_SimpleFunction('generateUrl', function ($route,$params = array()) use($app) {
				return $app->getRouter()->generateUrl($route,$params);
			}));
			$this->twig->addFunction(new \Twig_SimpleFunction('asset', function ($resource) use($rootPath) {
				return $rootPath . '/' . $resource;
			}));
			
			// Defining global variables
			$this->twig->addGlobal('appRoot',$this->rootPath);
			$this->twig->addGlobal('appUrl','http://' . $this->getHost() . '/' . $this->rootPath);
			$this->twig->addGlobal('appBase',strlen($this->rootPath) > 1 ? $this->rootPath . '/' : '/');
			$this->twig->addGlobal('identity',$this->identity);
		}
		return $this->twig;
	}
	
	/**
	 * Running the application.
	 */
	public function run(){
		// Checking singleton when running.
		if(self::$runningApplication !== null){
			throw new \Exception('Can\'t run multiple applications on the same request.');
		}else{
			self::$runningApplication = $this;
		}
		session_start();
		
		// Building the root path (client-side)
		// TODO check if working
		$lastSlash = strrpos($_SERVER['SCRIPT_NAME'],'/');
		$this->rootPath = substr($_SERVER['SCRIPT_NAME'],0,$lastSlash);
		
		try{
			// Creating request and response objets
			$this->request = new Request($this);
			$this->response = new Response();
			
			// Creating the router
			$this->router = $this->getRouter();
			
			if($this->configuration->getValue('maintenance') === 'yes'){
				// If the application is currently under maintenance, we
				// just show the message specified in the config.
				$cfgMessage = $this->configuration->getValue('maintenancemessage');
				$message = empty($cfgMessage) ? 'Application under maintenance.' : $cfgMessage;
				$this->response->setContent($this->showError('Maintenance',$message));
			}else{
				// Getting the appropriate action for the request
				$route = $this->router->getRoute($this->request);
			
				$controllerType = $this->getNamespace() . '\\Controller\\' . $route['controller'] . 'Controller';
				$this->controller = new $controllerType($this); 
				
				// Executing the action
				$controllerResult = $this->controller->perform($route['action']);
				
				$this->response->setContent($controllerResult);
			}
		}catch(Exception $ex){
			// Adding headers for HTTP Exceptions
			if($ex instanceof \common\Exception\HttpException){
				$ex->apply($this->response);
				$message = $ex->getMessage();
			}else{
				// For generic exceptions, we don't display the error message,
				// for security reasons.
				$this->response->addHeader('HTTP/1.0 500 Server error');
				$message = 'Server error. Please try again later or contact the server administrator.';
			}
			
			// On a local environment, we display the actual message.
			if(ENV_LOCAL || $this->debugMode()){
				$message = $ex->getMessage();
				
				$message .= '<h3>Stack trace:</h3>';
				$message .= $this->printStackTrace($ex->getTrace());
			}
			
			// Adding a contact email
			$message .= '<br />Please email us at ' . $this->configuration->getValue('email') . ' to let us know about the problem.';
			
			// Making a nice HTML error.
			$htmlError = $this->showError('Application error',$message);
			$this->response->setContent($htmlError);
			
			// Adding a log and a report
			$this->addLog(get_class($ex).' at action ' . isset($route) ? $route['action'] : '<unknown>' . ' : ' . $ex->getMessage() . ' (file: ' . $ex->getFile() . ', line: ' . $ex->getLine() . ')');
			$this->reportCrash($ex,$action);
		}
		
		// Sending the response to the client
		$this->response->render();
	}
	
	/**
	 * Returns the specified execution stack in HTML format.
	 */
	private function printStackTrace($stack){
		$res = '<ul>';
		foreach($stack as $s){
			$res .= '<li>';
			if(isset($s['class'])){
				$res .= $s['class'] . '::';
			}
			$res .= $s['function'] . '() (' . $s['file'] . ':' . $s['line'] . ')';
			$res .= '</li>';
		}
		$res .= '</ul>';
		return $res;
	}
	
	/**
	 * Getting the Request object and its parameters.
	 */
	public function getRequest(){
		return $this->request;
	}
	
	/**
	 * Getting the Response object that will be rendered to the client.
	 */
	public function getResponse(){
		return $this->response;
	}
	
	/**
	 * Adding an entry to the log file
	 */
	public function addLog($log){
		// Writing the log to the file
		$log = date('Y-m-d H:i:s') . ' : '.$log."\n";
		if(file_put_contents($this->getLogFilePath(),$log,FILE_APPEND) === false){
			header('HTTP/1.0 500 Server error');
			die('Couldn\'t write on the log file.');
		}
	}
	
	/**
	 * Getting the entire log file. Should be used with caution in production though.
	 */
	public function getLog(){
		$logFile = $this->getLogFilePath();
		
		if(!$log = file_get_contents($logFile)){
			throw new \common\Exception\HttpException(400,'Couldn\'t read log file.');
		}else{
			return $log;
		}
	}
	
	/**
	 * Getting the logs file path.
	 */
	private function getLogFilePath(){
		return LOG_FOLDER.'/'.$this->name.'.txt';
	}
	
	/**
	 * Getting a $_SESSION var
	 */
	public function getSessionVar($var){
		if(!isset($_SESSION[$var]))
			return null;
		
		return $_SESSION[$var];
	}
	
	/**
	 * Setting a $_SESSION var
	 */
	public function setSessionVar($var,$value){
		$_SESSION[$var] = $value;
	}
	
	/**
	 * Getting the application's name.
	 */
	public function getName(){
		return $this->name;
	}
	
	/**
	 * Getting the application root directory.
	 * The application root directory is the path from which it will be 
	 * executed. Therefore it should be used only for assets.
	 */
	public function getRootDir(){
		return $this->rootPath;
	}
	
	/**
	 * Getting a path without the application's root directory.
	 */
	public function getPathFromRootDir($path){
		if(strlen($this->rootPath) > 0 && strpos($path, $this->rootPath) === 0){
			$return = $path;
			if(strlen($this->rootPath) > 1){
				$return = substr($path,strlen($this->rootPath));
			}else{
				// If the root path is just a slash, we have to remove it.
				$return = substr($path,1);
			}
			
			return $return;
		}else{
			return $path;
		}
	}
	
	/**
	 * Getting the host name (without http://)
	 */
	public function getHost(){
		return $_SERVER['HTTP_HOST'];
	}
	
	/**
	 * Getting the application's configuration.
	 */
	public function getConfiguration(){
		return $this->configuration;
	}
	
	/**
	 * Getting the application path on the server.
	 */
	public function getFolder(){
		return COMMON_ROOT . '/apps/' . $this->name;
	}
	
	/**
	 * Gets the current running app.
	 */
	public static function getRunningApplication(){
		return self::$runningApplication;
	}
	
	/**
	 * 
	 */
	public function getRecap(){
		throw new \Exception('No recap defined.');
	}
	
	/**
	 * 
	 */
	public function getCharts(){
		throw new \Exception('No charts defined.');
	}
	
	/**
	 * Getting the application router.
	 */
	public function makeRouter(){
		$class = str_replace($this->name . 'Application','Routing\\' . $this->name . 'Router',get_class($this));
		return new $class($this);
	}
	
	public function getRouter(){
		if($this->router === null){
			$this->router = $this->makeRouter();
		}
		return $this->router;
	}
	
	/**
	 * Getting the application controller.
	 */
	private function getController($name = null){
		if($name == null){
			$name = $this->name;
		}
		
		$class = str_replace($this->name . 'Application','Controller\\' . $name . 'Controller',get_class($this));
		return new $class($this);
	}
	
	/**
	 * Making a crash report : adds a line to the admin application log.
	 */
	protected function reportCrash(\Exception $ex,$action){
		// Creating report folder if needed
		$crashFolder = REPORT_FOLDER;
		if(!file_exists($crashFolder)){
			mkdir($crashFolder,0777,true);
		}
		
		// Creating the report
		$report = "\n--------------------------------------\n";
		$report .= 'Application=' . $this->getName() . "\n";
		$report .= 'Action=' . $action . "\n";
		$report .= 'Type=' . get_class($ex) . "\n";
		$report .= 'Message=' . $ex->getMessage() . "\n";
		$report .= 'URI=' . $this->request->getRequestedURI() . "\n";
		$report .= 'Referer=' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '<unknown>') . "\n\n";
		
		foreach($ex->getTrace() as $s){
			$report .= ' - ';
			if(isset($s['class'])){
				$report .= $s['class'] . '::';
			}
			$report .= $s['function'] . '() (' . $s['file'] . ':' . $s['line'] . ')';
			$report .= "\n";
		}
		
		// Writing it
		$crashFile = $crashFolder . '/crash-' . time() . '-' . $this->getName();
		file_put_contents($crashFile,$report,FILE_APPEND);
	}
	
	/**
	 * Gets the authentication manager for the application.
	 */
	public final function getAuthenticationManager(){
		if($this->authManager === null){
			$this->authManager = $this->makeAuthenticationManager();
		}
		
		return $this->authManager;
	}
	
	/**
	 * Creates the authentication manager to use for the application and returns it.
	 */
	public function makeAuthenticationManager(){
		throw new \common\Exception\HttpException(500,'Authentication manager is not defined.');
	}
	
	/**
	 * Gets the user's identity.
	 */
	public function getIdentity(){
		if($this->identity === null){
			try{
				// Catching any exception in order to return a null identity.
				$this->identity = $this->getAuthenticationManager()->getIdentity();
			}catch(\Exception $e){}
		}
		return $this->identity;
	}
	
	public function getResourceFolder(){
		return $this->getFolder() . '/Resource';
	}
	
	public function getViewFolder(){
		return $this->getResourceFolder() . '/view';
	}
	
	public function getConfigFolder(){
		return $this->getResourceFolder() . '/config';
	}
	
	public function getSqlFolder(){
		return $this->getResourceFolder() . '/sql';
	}
	
	
	/**
	 * Method that returns a nice error message formatted with HTML and CSS.
	 */
	private function showError($title,$message){
		return '<!DOCTYPE html>
			<html>
				<head>
					<title>'.$title.'</title>
				</head>
				
				<body>
					<div style="width: 40%; margin: auto; border: 1px solid red; background-color: rgba(255,0,0,0.3); padding: 10px; font-family: Arial; font-size: 0.8em;">
						<h1 style="margin: 0px;">' . $title . '</h1>
						<p>' . $message . '</p>
					</div>
				</body>
			</html>
		';
	}
	
	public function getNamespace(){
		return str_replace('\\' . $this->name . 'Application','',get_class($this));
	}
	
	public function debugMode(){
		return false;
	}
	
	public function getDefaultController(){
		return $this->name;
	}
	
	public function getCacheFolder(){
		return CACHE_FOLDER . '/' . $this->name;
	}
}
