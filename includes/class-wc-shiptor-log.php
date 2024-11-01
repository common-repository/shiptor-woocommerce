<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Shiptor_Log {

    protected static $log_dir;

    protected static $log_base_name = 'shiptor-common-log';

    public static function init() {
        if(!is_dir( WC_SHIPTOR_LOG_DIR )){
            mkdir( WC_SHIPTOR_LOG_DIR, 0775);
        }

        self::$log_dir = WC_SHIPTOR_LOG_DIR;
    }

    public static function add($data, $date = null) {
        $dataArray = self::get($date);

        if (true === is_array($dataArray)) {
            $dataArray = array_merge($dataArray, $data);
        } else {
            $dataArray = $data;
        }

        file_put_contents(self::logPath($date), wp_json_encode($dataArray));
    }

    private static function get($date = null) {
        if  (true === file_exists(self::logPath($date)) ) {
            $file = file_get_contents(self::logPath());
            return json_decode($file, true);
        } else {
            return false;
        }
    }

    public static function getLogDir(){
        return self::$log_dir;
    }

    public static function getLogName($date = null){
        $name = self::$log_base_name . '-';
        $name .= $date ?
            ( new DateTime($date, new DateTimeZone( wp_timezone_string() )) )->format('Y-m-d')
            : current_time('Y-m-d');
        $name .= '.json';

        return sanitize_file_name($name);
    }

    public static function logPath($date = null){
        return self::getLogDir().'/'.self::getLogName($date);
    }

    public static function prepareRecord($request, $response, $datetime = null){
        $record = [];
        $microtime = microtime();
        $datetime = $datetime ?
            ( new DateTime($datetime, new DateTimeZone( wp_timezone_string() )) )->format('Y-m-d H:i:s')
            : current_time('mysql');
        $record[ $microtime ] = [];

        $request['ts'] = isset($request['ts']) ? $request['ts'] : $datetime;
        $request['type'] = isset($request['type']) ? $request['type'] : 'request';

        $response['ts'] = isset($response['ts']) ? $response['ts'] : $datetime;
        $response['type'] = isset($response['type']) ? $response['type'] : 'response';

        if(isset($response['error'])){
            $response['error'] = isset($response['error']['message']) ? $response['error']['message'] : '';
            $response['id'] = 'JsonRpcClient.js';
        }

        $record[ $microtime ] = [
            'request' => $request,
            'response' => $response,
        ];

        return $record;
    }

    public static function getLogFiles(){
        $dir = self::getLogDir();
        $files = scandir($dir);

        $results = [];
        foreach($files as $key => $value){
            $path = $value;
            if(!is_dir($path)) {
                $results[ $path ] = WC_SHIPTOR_LOG_DIR_URL . '/' . $path;
            }
        }

        uasort($results, array('self', 'compareLogsName'));

        return $results;
    }

    public static function compareLogsName($a_value, $b_value){
        $base_name = self::$log_base_name;
        $a_check = preg_match("/{$base_name}-(\d{4}-\d{2}-\d{2})/", $a_value, $a_output_array);
        if(!$a_check){
            return 1;
        }

        $b_check = preg_match("/{$base_name}-(\d{4}-\d{2}-\d{2})/", $b_value, $b_output_array);
        if(!$b_check){
            return 1;
        }

        $a = strtotime($a_output_array[1]);
        $b = strtotime($b_output_array[1]);

        if ($a === $b) {
            return 0;
        }

        return ($a > $b) ? -1 : 1;
    }

    public static function clearLogs(){
        $files = glob(self::$log_dir . '/*');
            foreach($files as $file){
            if(is_file($file)){
                unlink($file);
            }
        }
    }
}

return WC_Shiptor_Log::init();
