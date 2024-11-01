<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once WC_SHIPTOR_LIBRARIES_DIR . '/cache.class.php';

class WC_Shiptor_Cache {

    protected $cache_client;

    function __construct(){

        if(!is_dir( WC_SHIPTOR_CACHE_DIR )){
            mkdir( WC_SHIPTOR_CACHE_DIR, 0775);
        }

        $this->cache_client = new Cache();
        $this->cache_client->setCachePath(WC_SHIPTOR_CACHE_DIR);
        $this->cache_client->setCache('cache');
        $this->checkExpiration();
    }

    public function setCache($cache_name){
        $this->cache_client->setCache($cache_name);
        return $this;
    }

    public function store($key, $data, $expiration){
        $this->cache_client
            ->store($key, $data, $expiration);
    }

    public function retrieve($key){
        return $this->cache_client->retrieve($key);
    }

    public function clearCache(){
        $this->cache_client->eraseAll();
    }

    protected function checkExpiration(){
        $cache_last_check = get_option('woocommerce_shiptor_cache_last_check');
        if($cache_last_check){
            $cache_last_check = intval($cache_last_check);
            if(time() - $cache_last_check > 3600 ){
                $this->cache_client->eraseExpired();
                update_option('woocommerce_shiptor_cache_last_check', time());
            }
        } else {
            add_option('woocommerce_shiptor_cache_last_check', time());
        }
    }
}
