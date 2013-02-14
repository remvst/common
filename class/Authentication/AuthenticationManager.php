<?php
namespace common\Authentication;

use \common\Authentication\Identity as Identity;

/**
 * An AuthenticationManager is able to get the user's identity,
 * as well as logging him in and out.
 */
abstract class AuthenticationManager{
	/**
	 * Gets the identity object representing the user. If the user is not
	 * logged in, this method should return the default identity.
	 * @return The user's identity.
	 */
	public abstract function getIdentity();
	
	/**
	 * Gets the default identity : anonymous.
	 * Having a default Identity is useful to give certain permissions to
	 * anonymous users.
	 * @return The default identity.
	 */
	public abstract function getDefaultIdentity();
	
	/**
	 * Crypts the given password.
	 * @param $password The password to encrypt.
	 * @return The encrypted password.
	 */
	public abstract function cryptPassword($password);
	
	/**
	 * Tries to authenticate the user with the specified name and password.
	 * @param $name The user's name.
	 * @param $password The user's uncrypted password.
	 */
	public abstract function tryAuthenticate($name,$password);
	
	/**
	 * Logs the user out.
	 */
	public abstract function unauthenticate();
	
	/**
	 * Checks if the user has the right permissions.
	 * @param $identity The user's identity.
	 * @param $premissions The array of permissions which are required.
	 * @throws HttpException if the identity is null and permissions are required.
	 * @return true if the user has the right permissions.
	 */
	public function checkPermissions(Identity $identity,$permissions){
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
