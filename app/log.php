<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

/**
 * Description of log
 *
 * @author wtfribley
 */
class Log {

    public static function write($severity, $message){
        
        $line = date('m-d-Y hA:i:s') . ' [ ' . $severity . ' ] --> ' . $message . PHP_EOL;
        
        // Write to the log file.
        if($log = @fopen(PATH . 'logs/vault.log', 'a+'))
        {
            fwrite($log, $line);
            fclose($log);
        }
    }
    
    public static function exception($e){
        self::write('error', self::format($e));
    }

    private static function format($e){
        return $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
    }
    
}