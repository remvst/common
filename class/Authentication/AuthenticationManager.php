<?php
namespace common\Authentication;

abstract class AuthenticationManager{
	/**
	 * Gets the identity object representing the user.
	 */
	public abstract function getIdentity();
	
	/**
	 * Gets the default identity : anonymous.
	 */
	public abstract function getDefaultIdentity();
	
	/**
	 * Crypts the given password.
	 */
	public abstract function cryptPassword($password);
	
	/**
	 * Tries to authenticate the user with the specified name and password.
	 */
	public abstract function tryAuthenticate($name,$password);
	
	/**
	 * Logs the user out.
	 */
	public abstract function unauthenticate();
	
	/**
	 * Checks if the user has the right permissions.
	 */
	public function checkPermissions($identity,$permissions){
		if(!is_object($identity) && count($permissions) > 0){
			throw new \common\Exception\HttpException(500,'No identity specified.');
		}
		
		$userPermissions = $identity->getPermissionsArray();
		foreach($permissions as $p){
			if(!in_array($p,$userPermissions)){
				return false;
			}
		}
		return true;
	}
}
