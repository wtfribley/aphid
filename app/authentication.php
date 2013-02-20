<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Authentication {
    
    /**
     *  Compare data in the request to current session value to prevent CSRF.
     *  @param Request $request - passed by reference, modifies the request object.
     */
    public static function CSRF($request) {
    	$csrf_user = $request->options('data')['csrf'];
    
        if (!isset($csrf_user) || ($csrf_user != $request->session('csrf'))) {
            Error::page('401', $request);                  
        }
        // we have to unset the csrf element so it doesn't try to use it in subsequent queries.
        else if (isset($csrf_user) && ($csrf_user == $request->session('csrf')))
            unset($request->options['data']['csrf']);
            
        return $request;
    }
    
    /*
    *	Return the PasswordHash class.
    */
    public static function hasher() {
        // Hashing config variables.
        $hash_cost_log2 = 12;
        $hash_portable = false;      
        
        return new PasswordHash($hash_cost_log2, $hash_portable);
    }
    
    /*
    *   Generate a secure random token - from http://stackoverflow.com/a/13733588
    *   @param: int $length
    */
    public static function getToken($length = 16) {
        $token = '';
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $chars.= 'abcdefghijklmnopqrstuvwxyz';
        $chars.= '0123456789';
        for ($i=0;$i<$length;$i++) {
            $token.= $chars[static::secure_rand_num(0,strlen($chars))];        
        }
        return $token;
    }
   
    /*
    *   Generate a secure random number between $min and $max.
    *       from http://stackoverflow.com/a/13733588
    */
    private static function secure_rand_num($min, $max) {
        $range = $max - $min;
        if ($range < 0) return $min; // not so random...
        $log = log($range, 2);
        $bytes = (int) ($log / 8) + 1; // length in bytes
        $bits = (int) $log + 1; // length in bits
        $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
            $rnd = $rnd & $filter; // discard irrelevant bits
        } while ($rnd >= $range);
        return $min + $rnd;    
    }
}