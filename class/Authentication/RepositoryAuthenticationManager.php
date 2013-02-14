<?php
namespace common\Authentication;

/**
 * Authentication manager based on both repository 
 * and $_SESSION variables.
 */
abstract class RepositoryAuthenticationManager extends \common\Authentication\AuthenticationManager{
	const PREFIX = 'auth-';
	
	private $identity;
	
	public function __construct(){
		$this->identity = null;
	}
	
	/**
	 * Gets the user's identity. If a $_SESSION var is available,
	 * the system gets the Identity object. If no Identity is 
	 * available, the default Identity is returned.
	 * Calling this method multiple times should cause no 
	 * performance issues, since the check is performed only 
	 * once and the Identity is then stored.
	 */
	public function getIdentity(){
		if($this->identity === null){
			if(isset($_SESSION[self::PREFIX.'id'])){
				$id = $_SESSION[self::PREFIX.'id'];
				
				// Getting the repository
				$repo = \common\Data\Repository::getRepository($this->getEntityType());
				
				$res = $repo->find(null,array('id'=>(int)$id));
				if($res === null || count($res) == 0){
					unset($_SESSION[self::PREFIX.'id']);
					$this->identity = $this->getDefaultIdentity();
				}else{
					$this->identity = $res[0];
				}
			}else{
				$this->identity = $this->getDefaultIdentity();
			}
		}
		return $this->identity;
	}
	
	/**
	 * Tries to authenticate the user.
	 */
	public function tryAuthenticate($name,$clearPassword){
		$cryptedPassword = $this->cryptPassword($clearPassword);
				
		// Getting the repository
		$repo = \common\Data\Repository::getRepository($this->getEntityType());
		
		$where = array(
			'name' => $name,
			'password' => $cryptedPassword
		);
		$res = $repo->find(null,$where);
		
		if(count($res) == 0){
			throw new \common\Exception\AuthenticationException('Authentication failed.');
		}else if(count($res) > 1){
			throw new \common\Exception\AuthenticationException('Multiple identities possible.');
		}else{
			$this->identity = $res[0];
			$_SESSION[self::PREFIX.'id'] = $this->identity->getId();
		}
	}
	
	/**
	 * Logs the user out.
	 */
	public function unauthenticate(){
		// Changing the user's identity to the default one.
		$this->identity = $this->getDefaultIdentity();
		
		// Unsetting session var
		unset($_SESSION[self::PREFIX.'id']);
	}
	
	/**
	 * Gets the entity to use for the repository.
	 * @return The identity type.
	 */
	public abstract function getEntityType();
}
