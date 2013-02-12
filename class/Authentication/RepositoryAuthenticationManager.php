<?php
namespace common\Authentication;

abstract class RepositoryAuthenticationManager extends \common\Authentication\AuthenticationManager{
	const PREFIX = 'auth-';
	
	private $identity;
	
	public function __construct(){
		$this->identity = null;
	}
	
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
	
	public function unauthenticate(){
		// Changing the user's identity to the default one.
		$this->identity = $this->getDefaultIdentity();
		
		// Unsetting session var
		unset($_SESSION[self::PREFIX.'id']);
	}
	
	/**
	 * Gets the entity to use for the repository.
	 */
	public abstract function getEntityType();
}
