<?php
namespace common\Util;

/**
 * Decoder is simply used to encode Ajax queries.
 * The encoding is not flawless, but it should 
 * prevent most users from trying to send wrong
 * data.
 */
class Decoder{
    /**
     * Decoding the received parameter string.
	 * @param $chaine The encoded string.
	 * @return The decoded string.
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
     * Converts the decoded string to an array.
	 * @param $chaine The string to parse.
	 * @return The array of parameters.
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
