<?php
namespace common\Engine; 

/**
 * The Router class is used to determine which action of the Controller
 * should be used for the requested path.
 * There should be one Router for each Application. If there is a need
 * for several types of Router, Application::makeRouter() should be
 * overridden.
 * Each Router should be named as <ApplicationName>Router.
 * Basically, all you have to do is to override the getRoutes() method,
 * which should return all application routes. The getRoute() method 
 * will then be called by the parent Application and will try to find
 * a route which has the same pattern as the requested path.
 */
abstract class Router{
	protected $application;
	
	private $urlParams;
	private $routes;
	protected $route;

	/**
	 * Building a new router.
	 * Calls getRoutes().
	 * @param $app The parent Application.
	 */
	public function __construct(\common\Engine\Application $app){
		$this->application = $app;
		$this->urlParams = array();
		$this->routes = $this->getRoutes();
		$this->route = null;
	}
	
	/**
	 * Getting the right route for the specified request.
	 * If the method is not overridden, it will use the route defined in
	 * the getRoutes() method.
	 * Though, if you need to perform checks algorithmically, you can 
	 * redefine this method in your subclass.
	 * Returns an array with the action and the controller to use.
	 * @param $request The Request object.
	 * @return The array representing the route, with at least two keys: controller, and action.
	 */
	public function getRoute($request){
		if($this->route === null){
			$path = substr($request->getRequestedPath(),1);
			
			$this->route = $this->getRouteForPath($path);
			
			if($this->route === null){
				$this->application->addLog('No route for ' . $request->getRequestedURI());
				throw new \common\Exception\HttpException(404,'Page not found (request: ' . $request->getRequestedURI() . ').');
			}
		}
		return $this->route;
	}
	
	public function getRouteForPath($path){
		foreach($this->routes as $name=>$route){
			if($this->checkPathMatch($route,$path)){
				$route['name'] = $name;
				return $route;
			}
		}
		return null;
	}
	
	/**
	 * Checks if the specified pattern matches the specified path.
	 * If the pattern is matched, the function identifies the parameters
	 * and stores if the the $urlParams array.
	 * @param $route The route to check, without the initial slash.
	 * @param $path The path to check, without the initial slash.
	 * @return true if the pattern matches.
	 */
	public function checkPathMatch($route,$path){
		$pattern = $route['pattern'];
		
		// Replacing "*"
		$regexPattern = str_replace('*','(.*)',$route['pattern']);
		
		// Replacing parameters by their pattern.
		if(isset($route['requirements'])){
			foreach($route['requirements'] as $param=>$pattern){
				$regexPattern = str_replace('{' . $param . '}',$pattern,$regexPattern);
			}
		}
		
		// Replacing unspecified params
		$regexPattern = preg_replace('#\{[a-zA-Z]*\}#','.+',$regexPattern);
		
		//echo $regexPattern;
		$regexPattern = '#^'.$regexPattern.'$#';
		
		if(!preg_match($regexPattern,$path)){
			return false;
		}else{
			$varNames = array();
			$matches = array();
			
			// Getting all var names from the pattern.
			preg_match_all('#\{([a-zA-Z]*)\}#',$route['pattern'],$matches);
			
			$i = 0;
			foreach($matches[1] as $v){
				$varNames[] = $v;
			}
			
			// Formatting the pattern to get the vars
			$regexPattern = str_replace('*','.*',$route['pattern']);
			
			// Replacing parameters by their pattern.
			if(isset($route['requirements'])){
				foreach($route['requirements'] as $param=>$pattern){
					$regexPattern = str_replace('{' . $param . '}','('.$pattern.')',$regexPattern);
				}
			}
			
			// Unspecified params
			$regexPattern = preg_replace('#\{([a-zA-Z]*)\}#','(.*)',$regexPattern);
			$regexPattern = '#'.$regexPattern.'#';
			
			// Getting the values
			preg_match_all($regexPattern,$path,$matches,PREG_PATTERN_ORDER);
			
			// Storing them
			$this->urlParams = array();
			$nMatches = count($matches);
			for($i = 1 ; $i < $nMatches ; ++$i){
				$this->urlParams[$varNames[$i-1]] = $matches[$i][0];
			}
			
			return true;
		}
	}
	
	/**
	 * Getting a parameter from the pattern of the route that is being 
	 * used. If the parameter is not specified, the function returns null.
	 * @param $paramName The parameter.
	 */
	public function getPatternParam($paramName){
		if(!isset($this->urlParams[$paramName])){
			return null;
		}else{
			return $this->urlParams[$paramName];
		}
	}
	
	/**
	 * Gets the routes to use. Each route specifies a pattern, an action,
	 * and a controller.
	 * This method should be redefined for each Router.
	 * @return An array of routes. The keys are the route names, and the values are arrays with 3 parameters : pattern, controller and action.
	 */
	public function getRoutes(){
		return null;
	}
	
	/**
	 * Generates an URL for the specified route.
	 * Works only if the route exists and is defined in the getRoutes()
	 * method.
	 * @param $routeName The name of the route.
	 * @param $params The parameters of the route.
	 * @return The generated URL.
	 */
	public function generateUrl($routeName,$params = array()){
		if(!isset($this->routes[$routeName])){
			throw new \Exception('Route ' . $routeName . ' not found');
		}
		
		$url = $this->routes[$routeName]['pattern'];
		$url = str_replace('*','',$url);
		foreach($params as $p=>$v){
			// Checking if the given parameter value matches the requirements. 
			if(isset($this->routes[$routeName]['requirements'][$p]) 
				&& !preg_match('#^' . $this->routes[$routeName]['requirements'][$p] . '$#',$v)){
				throw new \common\Exception\HttpException(500,'Router error. (' . $p . '=' . $v . ')');
			}
			
			$url = str_replace('{'.$p.'}',$v,$url);
		}
		
		$url = $this->application->getRootDir() . '/' . $url;
		
		return $url;
	}
}
