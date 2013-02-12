<?php
namespace common\Authentication;

abstract class Identity extends \common\Data\Data{
	protected $name;
	protected $password;
	protected $isDefault;
	
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
	 */
	public function isDefaultIdentity(){
		return $this->isDefault;
	}
	
	public abstract function getPermissionsArray();
}
