<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

/**
 * Description of log
 *
 * @author wtfribley
 */
class Log {

    public static function write($code, $message){
        
        $line = date('m-d-Y hA:i:s') . ' [ ' . $code . ' ] --> ' . $message . PHP_EOL;
        
        // Write to the log file.
        $log = Config::get('system.log_file','aphid');
        if($log = @fopen(PATH . 'logs/' . $log . '.log', 'a+'))
        {
            fwrite($log, $line);
            fclose($log);
        }
    }
    
    public static function exception($e){
        static::write($e->getCode(), static::format($e));
    }

    private static function format($e){
        return $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
    }
    
}
