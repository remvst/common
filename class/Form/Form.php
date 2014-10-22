<?php
namespace common\Form;

class Form{
	private static $nextFormId = 1;

	private $formId;
	private $fields;
	private $entity;
	private $request;
	private $rendered;

	public function __construct($fields){
		$this->formId = self::$nextFormId++;
		$this->entity = null;
		$this->request = null;
		$this->rendered = array();
		
		$this->fields = array();
		foreach($fields as $fieldId => $fieldSettings){
			$this->addField($fieldId,$fieldSettings);
		}
		
		if(!isset($fields['form_submit'])){
			$this->addField('form_submit',array(
				'label' => '',
				'type' => 'submit',
				'value' => 'Submit'
			));
		}
		
		$this->addField('form_check_' . $this->formId,array(
			'label' => '',
			'type' => 'hidden',
			'value' => '1',
			'required' => true
		));
	}
	
	/**
	 * Testing if the form data is valid.
	 * Checks fields format.
	 */
	public function isValid(){
		$valid = true;
		foreach($this->fields as $fieldId => $fieldSettings){
			if(!$this->fieldIsValid($fieldId)){
				$valid = false;
			}
		}
	
		return $valid;
	}
	
	public function fieldIsValid($fieldId){
		// Required
		if(empty($this->fields[$fieldId]['value']) && $this->fields[$fieldId]['required']){
			return false;
		}
		
		// Pattern
		if(isset($this->fields[$fieldId]['pattern']) && !preg_match($this->fields[$fieldId]['pattern'],$this->fields[$fieldId]['value'])){
			return false;
		}
		
		return true;
	}
	
	public function wasSent(){
		$params = $this->request->getParameters('post');
		
		return (isset($params[$this->getUniqueFieldId('form_check_' . $this->formId)]));
	}
	
	public function setEntity($entity){
		$this->entity = $entity;
	}
	
	public function setRequest($request){
		$this->request = $request;
		
		// If the form was sent, we fill it with the values which were sent
		if($this->wasSent()){
			$params = $this->request->getParameters('post');
		
			$hydratation = array();
			
			foreach($this->fields as $fieldId => $fieldSettings){
				$id = $this->getUniqueFieldId($fieldId);
				
				$this->fields[$fieldId]['value'] = isset($params[$id]) ? $params[$id] : $this->fields[$fieldId]['value'];
				if($this->fields[$fieldId]['type'] == 'checkbox'){
					$this->fields[$fieldId]['value'] = isset($params[$id]) ? (bool)$this->fields[$fieldId]['value'] : false;
					$params[$id] = isset($params[$id]);
				}
				
				// Preparing the hydratation
				if($this->entity !== null && isset($params[$id])){
					$hydratation[$fieldId] = $this->fields[$fieldId]['value'];
				}
			}
			
			// Hydrating the entity
			if($this->entity !== null){
				$this->entity->hydrate($hydratation);
			}
		}
	}
	
	public function getUniqueFieldId($fieldId){
		return 'form_' . $this->formId . '_' . $fieldId;
	}
	
	public function renderForm(){
		$s = '<form method="post" action="">';
		foreach($this->fields as $fieldId=>$fieldSettings){
			$s .= $this->renderField($fieldId);
		}
		$s .= '</form>';
		return $s;
	}
	
	public function renderRest(){
		$s = '';
		foreach($this->fields as $fieldId => $fieldSettings){
			if(!$this->rendered[$fieldId]){
				$s .= $this->renderField($fieldId);
			}
		}
		return $s;
	}
	
	public function renderSubmitButton(){
		$this->rendered['submitted'] = true;
		return '<input type="submit" value="Submit" />';
	}
	
	public function renderField($fieldId){
		return $this->renderLabel($fieldId) . $this->renderInput($fieldId);
	}
	
	public function renderLabel($fieldId){
		return '<label for="' . $this->getUniqueFieldId($fieldId) . '">' . $this->fields[$fieldId]['label'] . '</label>';
	}
	
	public function renderInput($fieldId){
		$this->rendered[$fieldId] = true;
		
		switch($this->fields[$fieldId]['type']){
			case 'text':
			case 'submit':
			case 'password':
			case 'hidden':
				return '<input type="' . $this->fields[$fieldId]['type'] . '" id="' . $this->getUniqueFieldId($fieldId) . '" name="' . $this->getUniqueFieldId($fieldId) . '" value="' . $this->fields[$fieldId]['value'] . '" />';
				break;
			case 'textarea':
				$size = '';
				$size .= isset($this->fields[$fieldId]['cols']) ? ' cols="' . $this->fields[$fieldId]['cols'] . '"' : '';
				$size .= isset($this->fields[$fieldId]['rows']) ? ' rows="' . $this->fields[$fieldId]['rows'] . '"' : '';
				return '<textarea ' . $size . ' id="' . $this->getUniqueFieldId($fieldId) . '" name="' . $this->getUniqueFieldId($fieldId) . '">' . $this->fields[$fieldId]['value'] . '</textarea>';
				break;
			case 'checkbox':
				return '<input type="' . $this->fields[$fieldId]['type'] . '" id="' . $this->getUniqueFieldId($fieldId) . '" name="' . $this->getUniqueFieldId($fieldId) . '" value="1" ' . ($this->fields[$fieldId]['value'] ? 'checked="checked"' : '') . ' />';
				break;
			case 'multiple':
				echo print_r($this->fields[$fieldId]['value']);
				$s = '<select name="' . $this->getUniqueFieldId($fieldId) . '[]" multiple>';
				
				foreach($this->fields[$fieldId]['items'] as $item){
					if(is_array($item)){
						$value = $item['value'];
						$label = $item['label'];
					}else{
						$value = $item;
						$label = $item;
					}
					
					$selected = '';
					if(in_array($value,$this->fields[$fieldId]['value'])){
						$selected = 'selected="selected"';
					}
				
					$s .= '<option value="' . $value . '" ' . $selected . '>' . $label . '</option>';
				}
				
				$s .= '</select>';
				return $s;
				break;
		}
		
	}
	
	public function addField($fieldId,$settings){
		// Adding missing settings
		if(!isset($settings['value'])){
			$settings['value'] = '';
		}
		if(!isset($settings['label'])){
			$settings['label'] = ucwords($fieldId);
		}
		if(!isset($settings['required'])){
			$settings['required'] = false;
		}
		if(!isset($settings['type'])){
			$settings['type'] = 'text';
		}
	
		$this->fields[$fieldId] = $settings;
		
		$this->rendered[$fieldId] = false;
	}
}