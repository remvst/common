<?php
namespace common\Engine;

use \Exception as Exception;

/**
 * Application : main class for any website.
 * Basically, each website is represented by its own application.
 * For instance, if you wish to create a new website called "MyWebsite",
 * you should create the MyWebsiteApplication in the apps/MyWebsite folder.
 * Then you should create the following folders and classes :
 * - Controller/
 *   - MyWebsiteController.php
 * - Routing/
 *   - MyWebsiteRouter.php
 * - Data/
 * - Resource/
 *   - config/
 *   - view/
 *   - sql/ (optional)
 * 
 * The application basically does this:
 * - instanciates the application's Router to find which router should
 *   be used. Each route has its own pattern, and two parameters: the 
 *   controller's name, and the action to perform.
 * - once a route has been found, the controller performs the action/ 
 * - eventually, the application renders the result.
 * 
 * Therefore, the Application is the center of every website.
 * 
 * When you create your application, you should not have to override
 * any method. All you will need is then to create a new Router, and
 * at least a default Controller.
 */
abstract class Application{
	
	private static $runningApplication = null;
	
	private $rootPath;
	
	protected $name;
	protected $configuration;
	protected $router;
	protected $controller;
	protected $response;
	protected $request;
	protected $instantiated;
	
	protected $identity;
	protected $authManager;
	
	protected $twig;
	protected $db;
	
	/**
	 * Building the application : creates both the router and the controller,
	 * and executes the right action.
	 */
	public function __construct($cfg = null){
		// Getting application name from the class name
		$split_name = explode('\\',get_class($this));
		$this->name = str_replace('Application','',end($split_name));
		
		// Adding application-specific configuration
		$this->configuration = $cfg;
		$this->configuration->fuse(IniConfiguration::buildDirectory($this->getConfigFolder()));
		
		// Initializing several variables.
		$this->twig = null;
		$this->authManager = null;
		$this->identity = null;
		$this->db = null;
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
			
			// Adding generateUrl() and asset() functions, and the truncate filter.
			$rootPath = $this->rootPath;
			$router = $this->router;
			$this->twig->addFunction(new \Twig_SimpleFunction('generateUrl', function ($route,$params = array()) use($router) {
				return $router->generateUrl($route,$params);
			}));
			$this->twig->addFunction(new \Twig_SimpleFunction('asset', function ($resource) use($rootPath) {
				return $rootPath . '/' . $resource;
			}));
			$this->twig->addFilter(new \Twig_SimpleFilter('truncate', function ($str,$length) {
				if(strlen($str) > $length)
					return substr($str,0,$length) . '...';
				else
					return $str;
			}));
			
			// Defining global variables
			$this->twig->addGlobal('appRoot',$this->rootPath);
			$this->twig->addGlobal('appUrl','http://' . $this->getHost() . '/' . $this->rootPath);
			$this->twig->addGlobal('appBase',strlen($this->rootPath) > 1 ? $this->rootPath . '/' : '/');
			$this->twig->addGlobal('identity',$this->identity);
			$this->twig->addGlobal('localEnv',ENV_LOCAL);
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
		$u = isset($_SERVER['REDIRECT_URL']) ? $_SERVER['REDIRECT_URL'] : $_SERVER['SCRIPT_NAME'];
		$u = $_SERVER['SCRIPT_NAME'];
		
		$scriptFolder = substr($_SERVER['SCRIPT_NAME'],0,strrpos($_SERVER['SCRIPT_NAME'],'/'));
		
		if(isset($_SERVER['REDIRECT_URL']) && !empty($scriptFolder) && strpos($_SERVER['REDIRECT_URL'],$scriptFolder) === false){
			$script = $_SERVER['SCRIPT_NAME'];
			$uri = substr($script,0,strrpos($script,'/'));
			
			$uri = strrev($uri);
			$redir = strrev($_SERVER['REDIRECT_URL']);
			
			$common = '';
			$n = min(strlen($uri),strlen($redir));
			$i = 0;
			while($i < $n && $uri[$i] == $redir[$i]){
				echo $uri[$i] . ' - ' . $redir[$i] . "\n";
				$i++;
			}
			
			$common = strrev(substr($redir,0,strlen($redir) - $i));
			$u = $common;
		}
		
		$lastSlash = strrpos($u,'/');
		$this->rootPath = substr($u,0,$lastSlash);
		
		try{
			// Creating request and response objects
			$this->request = new Request($this);
			$this->response = new Response();
			
			if($this->configuration->getValue('maintenance') === 'yes'){
				// If the application is currently under maintenance, we
				// just show the message specified in the config.
				$cfgMessage = $this->configuration->getValue('maintenancemessage');
				$message = empty($cfgMessage) ? 'Application under maintenance.' : $cfgMessage;
				$this->response->setContent($this->showError('Maintenance',$message));
			}else{
				// Creating the router
				$this->router = $this->getRouter();
				
				// Getting the appropriate action for the request
				$route = $this->router->getRoute($this->request);
				
				// Adding history
				$location = $route['controller'] . ':' . $route['action'] . ' (' . $this->request->getRequestedUri() . ')';
				$this->addHistory($location);
				
				// Creating the controller
				$controllerType = $this->getNamespace() . '\\Controller\\' . $route['controller'] . 'Controller';
				$this->controller = new $controllerType($this); 
				
				// Executing the action and storing the result into the response object
				$this->response->setContent($this->controller->perform($route['action']));
			}
		}catch(\Exception $ex){
			if($ex instanceof \common\Exception\HttpException){
				// Adding headers for HTTP Exceptions
				$ex->apply($this->response);
				$message = $ex->getMessage();
				$title = 'Error ' . $ex->getErrorCode();
				$type = 'http-' . $ex->getErrorCode();
			}else{
				// For generic exceptions, we don't display the error message,
				// for security reasons.
				$this->response->addHeader('HTTP/1.0 500 Server error');
				$message = 'Server error. Please try again later or contact the server administrator.';
				$title = 'Application error';
				$type = 'unknown';
			}
			
			// Adding a contact email
			$message .= '<br />Please email us at ' . $this->configuration->getValue('email') . ' to let us know about the problem.';
			
			// On a local environment, we display the actual message.
			if(ENV_LOCAL || $this->debugMode()){
				$message .= '<br /><br />Exception message: '.$ex->getMessage();
				
				$message .= '<h3>Stack trace:</h3>';
				$message .= $this->printableStackTrace($ex->getTrace());
			}
			
			// Making a nice HTML error.
			$htmlError = $this->showError($title,$message);
			$this->response->setContent($htmlError);
			
			if(!isset($location)){
				$location = 'unspecified';
			}
			
			// Adding a log and a report
			// TMP disabling report for 404
			if(!($ex instanceof \common\Exception\HttpException) || $ex->getErrorCode() != 404){
				$this->report($ex,$location,$type);
			}
			$this->addLog(get_class($ex).' at ' . $location . ' : ' . $ex->getMessage() . ' (file: ' . $ex->getFile() . ', line: ' . $ex->getLine() . ')');
		}
		
		// Sending the response to the client
		$this->response->render();
	}
	
	/**
	 * Returns the specified execution stack in HTML format.
	 * @param $stack The stack to trace.
	 * @return The HTML-formatted stack trace.
	 */
	private function printableStackTrace($stack){
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
	 * Returns null if the application is not the running one.
	 * @return The Request object.
	 */
	public function getRequest(){
		return $this->request;
	}
	
	/**
	 * Getting the Response object that will be rendered to the client.
	 * Returns null if the application is not the running one.
	 * @return The Response object.
	 */
	public function getResponse(){
		return $this->response;
	}
	
	/**
	 * Adding an entry to the log file.
	 * @param $log The log content.
	 * @throws Exception if unable to write on the log file.
	 */
	public function addLog($log){
		// Writing the log to the file
		$log = date('Y-m-d H:i:s') . ' : '.$log."\n";
		// if(file_put_contents($this->getLogFilePath(),$log,FILE_APPEND) === false){
		// 	throw new \Exception('Couldn\'t write on the log file.');
		// }
	}
	
	/**
	 * Getting the entire log file. Should be used with caution in production though.
	 * @return The content of the log file.
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
	 * @return The path.
	 */
	private function getLogFilePath(){
		return LOG_FOLDER.'/'.$this->name.'.txt';
	}
	
	/**
	 * Getting a $_SESSION var
	 * @param $var The name of the variable.
	 * @return The content of the variable, null if undefined.
	 */
	public function getSessionVar($var){
		if(!isset($_SESSION[$var]))
			return null;
		
		return $_SESSION[$var];
	}
	
	/**
	 * Setting a $_SESSION var.
	 * @param $var The name of the variable.
	 * @param $value The content of the variable.
	 */
	public function setSessionVar($var,$value){
		$_SESSION[$var] = $value;
	}
	
	/**
	 * Getting the application's name.
	 * @return The name of the application.
	 */
	public function getName(){
		return $this->name;
	}
	
	/**
	 * Getting the application root directory.
	 * The application root directory is the path from which it will be 
	 * executed. Therefore it should be used only for assets.
	 * @return The path.
	 */
	public function getRootDir(){
		return $this->rootPath;
	}
	
	/**
	 * Getting a path without the application's root directory.
	 * @param $path The path to process.
	 * @return The original path, without the application's root.
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
	 * @return The host.
	 */
	public function getHost(){
		return $_SERVER['HTTP_HOST'];
	}
	
	/**
	 * Getting the application's configuration.
	 * @return The IniConfiguration object.
	 */
	public function getConfiguration(){
		return $this->configuration;
	}
	
	/**
	 * Getting the application path on the server.
	 * @return The path.
	 */
	public function getFolder(){
		return COMMON_ROOT . '/apps/' . $this->name;
	}
	
	/**
	 * Gets the current running app.
	 * @return The running application.
	 */
	public static function getRunningApplication(){
		return self::$runningApplication;
	}
	
	/**
	 * Getting the application router. If you wish to
	 * change the Router type, you should override
	 * this method.
	 * @return The router.
	 */
	public function makeRouter(){
		$class = str_replace($this->name . 'Application','Routing\\' . $this->name . 'Router',get_class($this));
		return new $class($this);
	}
	
	/**
	 * Gets the application router.
	 * If you need to change the way the controller is created, you should
	 * override the makeRouter() method.
	 * @return The router.
	 */
	public function getRouter(){
		if($this->router === null){
			$this->router = $this->makeRouter();
		}
		return $this->router;
	}
	
	/**
	 * Getting the application controller.
	 * @param $name The controller's name. For instance, if Example, ExampleController will be loaded.
	 * @return The controller.
	 */
	protected function getController($name = null){
		if($name == null){
			$name = $this->getDefaultController();
		}
		
		$class = str_replace($this->name . 'Application','Controller\\' . $name . 'Controller',get_class($this));
		return new $class($this);
	}
	
	/**
	 * Making a crash report : adds a line to the admin application log.
	 * @param $ex The exception to log.
	 * @param $location A parameter specifying the application location (controller, action...)
	 * @param $type The type of report (used for quick display on the admin panel).
	 */
	protected function report(\Exception $ex,$location,$type = 'unknown'){
		// Creating report folder if needed
		$crashFolder = REPORT_FOLDER;
		if(!file_exists($crashFolder)){
			mkdir($crashFolder,0777,true);
		}
		
		// Creating the report
		$report = "\n--------------------------------------\n";
		$report .= 'Date=' . date('Y-m-d H:i:s') . "\n";
		$report .= 'Application=' . $this->getName() . "\n";
		$report .= 'Location=' . $location . "\n";
		$report .= 'Type=' . get_class($ex) . "\n";
		$report .= 'Message=' . $ex->getMessage() . "\n";
		
		$id = $this->getIdentity();
		
		$report .= 'Identity=' . ($id !== null ? $id->getName() : '<none>') . "\n";
		$report .= 'URI=' . $this->request->getRequestedURI() . "\n";
		$report .= 'Referer=' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '<unknown>') . "\n";
		$report .= 'UserAgent=' . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '<unknown>') . "\n";
		$report .= "History=\n";
		
		$history = $this->getHistory();
		foreach($history as $h){
			$report .= "\t- " . $h['date'] . ' : ' . $h['content'] . "\n";
		}
		
		$post = $this->request->getParameters('post');
		$get = $this->request->getParameters('get');
		
		if(count($post) > 0)
			$report .= "POST=\n" . print_r($post,true) . "\n\n";
		if(count($get) > 0)
			$report .= "GET=\n" . print_r($get,true) . "\n\n";
		
		$report .= "Stack trace=\n";
		foreach($ex->getTrace() as $s){
			$report .= "\t- ";
			if(isset($s['class'])){
				$report .= $s['class'] . '::';
			}
			$report .= $s['function'] . '() (' . $s['file'] . ':' . $s['line'] . ")\n";
		}
		
		// Writing it
		$crashFile = $crashFolder . '/crash-' . time() . '-' . $this->getName() . '-' . $type;
		// file_put_contents($crashFile,$report,FILE_APPEND);
	}
	
	/**
	 * Gets the authentication manager for the application.
	 * If you need to use an authentication manager, you 
	 * should override the makeAuthenticationManager() method.
	 * @return The AuthenticationManager object.
	 */
	public final function getAuthenticationManager(){
		if($this->authManager === null){
			$this->authManager = $this->makeAuthenticationManager();
		}
		
		return $this->authManager;
	}
	
	/**
	 * Creates the authentication manager to use for the application and returns it.
	 * @throws HttpException if no authentication manager is defined.
	 * @return The AuthenticationManager.
	 */
	public function makeAuthenticationManager(){
		throw new \common\Exception\HttpException(500,'Authentication manager is not defined.');
	}
	
	/**
	 * Gets the user's identity.
	 * If there is no authentication manager for the application, null is returned.
	 * @return The user identity, or null.
	 */
	public function getIdentity(){
		if($this->identity === null){
			try{
				// Catching any exception in order to return a null identity.
				$this->identity = $this->getAuthenticationManager()->getIdentity();
			}catch(\Exception $e){
				
			}
		}
		return $this->identity;
	}
	
	/**
	 * Getting the path to the folder containing resources.
	 * @return The folder's path.
	 */
	public function getResourceFolder(){
		return $this->getFolder() . '/Resource';
	}
	
	/**
	 * Getting the folder containing views (templates).
	 * @return The folder's path.
	 */
	public function getViewFolder(){
		return $this->getResourceFolder() . '/view';
	}
	
	/**
	 * Getting the folder containing configuration files.
	 * @return The folder's path.
	 */
	public function getConfigFolder(){
		return $this->getResourceFolder() . '/config';
	}
	
	/**
	 * Getting the folder containing .sql files.
	 * @return The folder's path.
	 */
	public function getSqlFolder(){
		return $this->getResourceFolder() . '/sql';
	}
	
	/**
	 * Method that returns a nice error message formatted with HTML and CSS.
	 * @param $title The error title.
	 * @param $message The error details.
	 * @return The output.
	 */
	protected function showError($title,$message){
		try{
			$controller = $this->controller !== null ? $this->controller : $this->getController();
			return $controller->showError($title,$message);
		}catch(\Exception $e){
			return '<!DOCTYPE html>
			<html>
				<head>
					<title>' . $title . '</title>
				</head>
				<body>
					<h1>' . $title . '</h1>
					<p>' . $message . '</p>
				</body>
			</html>';
		}
	}
	
	/**
	 * Getting the application's namespace.
	 * @return The namespace's string.
	 */
	public function getNamespace(){
		return str_replace('\\' . $this->name . 'Application','',get_class($this));
	}
	
	/**
	 * Checking if the application is in debug mode. Debug mode
	 * should only be used for the development process. However,
	 * you can enable it by overriding this method.
	 * @return true if debug mode is enabled.
	 */
	public function debugMode(){
		return ENV_LOCAL;
	}
	
	/**
	 * Getting the default controller's name.
	 * This is useful for common actions, like pageNotFound.
	 * @return The name.
	 */
	public function getDefaultController(){
		return $this->name;
	}
	
	/**
	 * Getting the folder containing the application's cache files.
	 * @return The folder's path.
	 */
	public function getCacheFolder(){
		return CACHE_FOLDER . '/' . $this->name;
	}
	
	/**
	 * Getting the application's database connection.
	 * @return The DB object.
	 */
	public function getDB(){
		if($this->db === null){
			$this->db = new \common\Data\DB(array(
				'host' => $this->configuration->getValue('dbhost'),
				'user' => $this->configuration->getValue('dbuser'),
				'pass' => $this->configuration->getValue('dbpass'),
				'name' => $this->configuration->getValue('dbname'),
				'port' => $this->configuration->getValue('dbport'),
			));
		}
		return $this->db;
	}
	
	/**
	 * Adding a line to the user's browsing history.
	 * @param $line The line to add.
	 */
	public function addHistory($line){
		$history = $this->getSessionVar('history' . $this->name);
		if($history == null){
			$history = array();
		}
		
		$history[] = array(
			'date' => date('Y-m-d H:i:s'),
			'content' => $line,
			'path' => $this->request->getRequestedPath()
		);
		
		$this->setSessionVar('history' . $this->name,$history);
	}
	
	/**
	 * Getting the user's browsing history.
	 * @return The array representing the history.
	 */
	public function getHistory(){
		$history = $this->getSessionVar('history' . $this->name);
		return ($history !== null ? $history : array());
	}
}
