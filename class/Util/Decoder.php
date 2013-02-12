<?php
namespace common\Util;
class Decoder{
    /**
     * Décryptage des paramètres reçus en URL
     * La clé est contenue dans le premier octet reçu.
     */
    public static function decode($chaine){
    	$chaineDecodee = $chaine;
    	
        // On commence par rendre l'URL "lisible"
        //$chaineDecodee = urldecode($chaine);
        $chaineDecodee = utf8_decode($chaineDecodee);
        
        // On récupère la clé au début de la chaine
        $cle = ord($chaineDecodee[0]);
       
        // Et on l'enlève de la partie à décoder
        $chaineDecodee = substr($chaineDecodee,1);
        
        $chaineDecryptee = '';
        for($i = 0 ; $i < strlen($chaineDecodee) ; $i++){
            $c = ord($chaineDecodee[$i]);
            $chaineDecryptee .= chr(($c-$cle+256)%256);
        }
        
        return $chaineDecryptee;
    }
    
    /**
     * Parsage : conversion des paramètres en tableau associatif
     */
    public static function parse($chaine){
        $res = array();
        
        $lignes = explode('|',$chaine);
        for($i = 0 ; $i < count($lignes) ; $i++){
            $param = explode('=',$lignes[$i]);
            if(count($param) == 2)
                $res[$param[0]] = $param[1];
        }
        
        return $res;
    }
}
