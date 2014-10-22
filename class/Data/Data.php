<?php
namespace common\Data;

/**
 * Any data which can be represented within the database.
 */
class Data{
    /**
     * ID. Is used for every single data unit.
     * If an entity has no ID, then there could be some problems with the repository.
     */
    protected $id;
    
    /**
     * Specifies whether the data already exists or should be created.
     */
    private $exists;
    
    /**
     * Building a new Data object
     */
    public function __construct($hydratation = null){        
        $this->id = null;
        $this->exists = false;
        if($hydratation !== null){
            $this->hydrate($hydratation);
        }
    }
    
    public function setExists($exists){
        $this->exists = $exists;
    }
    
    public function getId(){
        return $this->id;
    }
    
    public function setId($id){
        $this->id = $id;
    }
    
    /**
     * Hydrating the object with the specified set of values
     */
    public function hydrate($hydratation){
        foreach($hydratation as $attr=>$value){
            $function = 'set'.ucwords($attr);
			//echo $function . "(" . $value . ")\n";
            if(method_exists($this, $function)){
                $this->$function($value);
            }
        }
    }
    
    /**
     * Knowin whether the current data should be updated or created.
     */
    public function isNew(){
    	return !$this->exists;
    }

    public function toArray(){
        $methods = preg_grep('#^get#',get_class_methods(get_class($this)));

        $obj = array();
        foreach($methods as $m){
            $field = str_replace('get','',$m);
            $field[0] = strtolower($field[0]);
            $obj[$field] = $this->$m();
        }

        return $obj;
    }
}
