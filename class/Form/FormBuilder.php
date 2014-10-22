<?php
namespace common\Form;

abstract class FormBuilder{
	private $fields;
	private $entity;

	public function __construct($entity){
		$this->fields = $this->getFields();
		$this->entity = $entity;
		
		// Settings values
		foreach($this->fields as $fieldId => $fieldSettings){
			$method = 'get' . ucwords($fieldId);
			if(method_exists($entity,$method)){
				$this->fields[$fieldId]['value'] = $entity->$method();
			}
		}
	}

	public abstract function getFields();
	
	public function createForm(){
		$form = new Form($this->fields);
		$form->setEntity($this->entity);
		return $form;
	}
}