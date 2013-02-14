<?php
namespace common\Authentication;

/**
 * An Identity is an entity composed of a name and
 * password.
 */
abstract class Identity extends \common\Data\Data{
	protected $name;
	protected $password;
	protected $isDefault;
	
	/**
	 * Creates a new Identity object.
	 * @param $hydratation
	 * @param $isDefault Specifies whether the identity is the default one.
	 */
	public function __construct($hydratation,$isDefault = false){
		parent::__construct($hydratation);
		$this->isDefault = $isDefault;
	}
	
	public function getName(){
		return $this->name;
	}
	
	public function setName($name){
		$this->name = (string)$name;
	}
	
	public function getPassword(){
		return $this->password;
	}
	
	public function setPassword($password){
		$this->password = $password;
	}
	
	/**
	 * Returns true if this is the default (anonymous) identity.
	 * @return true if the identity is a default identity.
	 */
	public function isDefaultIdentity(){
		return $this->isDefault;
	}
	
	/**
	 * Returns an array of permissions which the identity has.
	 */
	public abstract function getPermissionsArray();
}
