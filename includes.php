<?php
// Defining a series of constants for the whole framework.
define('ENV_LOCAL',(in_array($_SERVER['REMOTE_ADDR'],array('127.0.0.1','::1')) || $_SERVER['REMOTE_ADDR'] == '::1'));
define('COMMON_ROOT',dirname(__FILE__));
define('APPS_FOLDER',COMMON_ROOT.'/apps');
define('CACHE_FOLDER',COMMON_ROOT.'/cache');
define('VENDOR_FOLDER',COMMON_ROOT.'/vendor');
define('REPORT_FOLDER',COMMON_ROOT.'/reports');
define('LOG_FOLDER',COMMON_ROOT.'/logs');
define('APP_EXEC_FOLDER',$_SERVER['DOCUMENT_ROOT'] . substr($_SERVER['SCRIPT_NAME'],0,strrpos($_SERVER['SCRIPT_NAME'],'/')));

// Displaying errors only if the script is executed locally.
ini_set('display_errors',ENV_LOCAL);
if(ENV_LOCAL){
	error_reporting(E_ALL);
}

// Managing uncaught exceptions.
set_exception_handler(function(){
	die('Unknown error.');
});

// UTF-8 encoding
header('Content-Type: text/html; charset=UTF-8');

/**
 * Initializing an app.
 * Creates an autoloader, then loads the configuration file, 
 * and finally creates and runs the application.
 */
function init_app($appName){
    // Using an autoloader to invoke framework classes (and app-specific classes).
	spl_autoload_register(function($class){
		// Framework classes
		$classFile = COMMON_ROOT . '/' . str_replace(array('\\','common/'),array('/','class/'),$class) . '.php';
		if(file_exists($classFile)){
			require $classFile;
			return;
		}
		
		// App-specific classes
		$classFile = APPS_FOLDER . '/' . str_replace('\\','/',$class) . '.php';
		if(file_exists($classFile)){
			require $classFile;
			return;	
		}
	});
		
    try{
		// Autoloader for vendor classes
		require 'vendor/autoload.php';
		
		// Getting application class
		require dirname(__FILE__) . '/apps/'.$appName.'/'.$appName.'Application.php';
	
        // Getting common configuration
        $cfg = common\Engine\IniConfiguration::buildDirectory(dirname(__FILE__) . '/config');
    
		// Creating the application
		$class = $appName.'\\' . $appName . 'Application';
		$app = new $class($cfg);
		$app->run();
    }catch(\Exception $e){
        header('HTTP/1.0 500 Server error');
        die('Server was unable to initialize the app.');
    }
}
