<?php
namespace common\Engine;
 
/**
 * Abstract class for controllers.
 * The controller is the intermediate between data and views. The controller
 * retrieves the data, and then renders the view with the data.
 * The controller is composed of several methods with the same kind of name.
 * For example, if you have a home page, you should define the homeAction()
 * in your controller.
 */
abstract class Controller{
	private $application;
	
	/**
	 * Constructor : requires the $application in order to initialize Twig.
	 */
	public function __construct($application){
		$this->application = $application;
	}
	
	/**
	 * Shortcut for twig or raw rendering.
	 * If the template has the *.twig extension, Twig will be used to 
	 * render it. Otherwise, the file will be returned as is.
	 */
	protected function render($template,$params = array()){
		// Checking if Twig is really needed with the template extension.
		$spl = explode('.',$template);
		if(end($spl) == 'twig'){
			return $this->application->getTwig()->render($template,$params);
		}else{
			return file_get_contents($this->application->getViewFolder() . '/'.$template);
		}
	}
	
	/**
	 * Renders the specified template from its cache. If the cache does
	 * not exist yet, it is created.
	 * This method should be used only for pages that do not change 
	 * very often, because the cache has to be cleared for every change.
	 * One template can have several cache files depending on its 
	 * content. The cache file to use is determined by the print. The 
	 * print should be a string specific to each different render of 
	 * the cache. Therefore it has to be chosen wisely to avoid having
	 * the same render for different pages.
	 * If the page uses data from the database, it would be wise not to 
	 * retrieve this data when the cache is available. Therefore, use 
	 * hasCache() before retrieving data for better performance.
	 */
	protected function renderFromCache($template,$params = array(),$print = null){
		//~ return $this->render($template,$params);
		$cacheFile = $this->getCacheFile($template,$print);
		$cacheDir = dirname($cacheFile);
		
		// Not using hasCache for optimization reason
		if(!file_exists($cacheFile)){
			if(!file_exists($cacheDir) && !mkdir($cacheDir,0755,true)){
				throw new \Exception('Unable to create cache directory : ' . $cacheDir);
			}
			
			$content = $this->render($template,$params);
			file_put_contents($cacheFile,$content);
			return $content;
		}else{
			return file_get_contents($cacheFile);
		}
	}
	
	/**
	 * Checks if the cache is available for the specified template and 
	 * print.
	 */
	protected function hasCache($template,$print = null){
		return file_exists($this->getCacheFile($template,$print));
	}
	
	/**
	 * Gets the path for the specified template and print cache file.
	 */
	private function getCacheFile($template,$print = null){
		$cacheDir = $this->application->getCacheFolder().'/views';
		$cacheFile = $cacheDir . '/' . $template . '.cache.' . md5($print);
		return $cacheFile;
	}
	
	/**
	 * Getting the controller's application
	 */
	public function getApplication(){
		return $this->application;
	}
	
	/**
	 * Action to get raw files. Available for all types of controllers. 
	 */
	public final function rawfileAction(){
		$path = $this->getApplication()->getRootDir() . '/' . $this->getApplication()->getRequest()->getRequestedURI();
		
		if(!file_exists($path)){
			return $this->pageNotFoundAction();
		}
		// TODO add the right header
		
		return file_get_contents($path);
	}
	
	/**
	 * When a page is not found
	 * Adds a log and sends a 404 response code.
	 */
	public function pageNotFoundAction(){
		$this->getApplication()->addLog('Page not found. URI: ' . $this->getApplication()->getRequest()->getRequestedURI());
		throw new \common\Exception\HttpException(404,'The page/file you requested was not found.');
	}
	
	/**
	 * Redirecting to another page.
	 */
	public final function redirect($path){        
		$this->getApplication()->getResponse()->addHeader('Location: ' . $path);
		return null;
	}
	
	/**
	 * Checks if the user has the specified permissions.
	 */
	protected function requiresPermissions($permissions){
		$authManager = $this->application->getAuthenticationManager();
		$identity = $this->application->getIdentity();
		if(!$authManager->checkPermissions($identity,$permissions)){
			throw new \common\Exception\AuthenticationException('Permissions required.');
		}
	}
	
	/**
	 * Logs the user out.
	 */
	public function unauthenticateAction(){
		$identity = $this->application->getIdentity();
		if($identity === null || $identity->isDefaultIdentity()){
			throw new \common\Exception\AuthenticationException('Already logged out.');
		}else{			
			$authManager = $this->application->getAuthenticationManager();
			$authManager->unauthenticate();
			return $this->getAuthenticationRedirectAction();
		}
	}
	
	/**
	 * Authenticating the user.
	 */
	public function authenticateAction(){
		// Checking if the user is already authenticated.
		$identity = $this->application->getIdentity();
		if($identity !== null && !$identity->isDefaultIdentity()){
			throw new \common\Exception\HttpException(400,'Already authenticated.');
		}
		
		// Getting sent parameters
		$params = $this->getApplication()->getRequest()->getParameters('post');
		
		if(isset($params['name']) && isset($params['password'])){
			// Form has been sent, we try to authenticate the user.
			try{
				$authManager = $this->application->getAuthenticationManager();
				$authManager->tryAuthenticate($params['name'],$params['password']);
				return $this->getAuthenticationRedirectAction();
			}catch(\common\Exception\AuthenticationException $e){
				throw new \common\Exception\HttpException(500,'Authentication failed.');
			}
		}else{
			// Displaying the form. Has to be defined for the application.
			return $this->render('login.html.twig');
		}
	}
	
	/**
	 * Gets the action to perform after authentication actions.
	 * @return The action to perform.
	 */
	public function getAuthenticationRedirectAction(){
		throw new \common\Exception\HttpException(500,'No authentication redirect action defined.');
	}
	
	/**
	 * Performs the specified action, after checking if 
	 * the right permissions were matched.
	 * @return The result of the action.
	 */
	public final function perform($action){
		$method_name = $action.'Action';
		if(!method_exists($this,$method_name)){
			throw new \common\Exception\HttpException(500,'Action ' . $action . ' does not exist.');
		}
		
		$permissions = $this->getPermissions();
		if(isset($permissions[$action]) || isset($permissions['*'])){
			$perms = isset($permissions[$action]) ? $permissions[$action] : $permissions['*'];
			
			$authManager = $this->application->getAuthenticationManager();
			$identity = $this->application->getIdentity();
			
			if(!$authManager->checkPermissions($identity,$perms)){
				if($identity === null || $identity->isDefaultIdentity()){
					// If the user is not logged in, we allow him to log in.
					return $this->authenticateAction();
				}else{
					throw new \common\Exception\AuthenticationException('You lack permissions for this page.');
				}
			}
		}
		
		// 
		return $this->$method_name();
	}
	
	/**
	 * Returns an array with the permissions for each action.
	 * You should redefine this method if you wish to have a 
	 * permission system.
	 * You can specify any permission you want for any action.
	 * @return An array with actions as keys, and arrays of permissions as values.
	 */
	public function getPermissions(){
		return array();
	}
	
	/**
	 * Generates a URL for the specified route. This is only a
	 * shortcut to Router::generateUrl().
	 * @param $route The route name.
	 * @param $params The route parameters.
	 * @return The generated URL.
	 */
	public function generateUrl($route,$params = array()){
		return $this->application->getRouter()->generateUrl($route,$params);
	}
}
