<?php
/**
 * SERPmetrics PHP-SDK
 */

class SMapi {

    const VERSION = 'v2.1.0';

    public static $apiUrl = 'api.serpmetrics.com';
    public static $userAgent = 'SERPmetrics PHP5 Library';
    public static $serializer = array('json_encode', 'json_decode');
    public static $retries = 3;

    protected $_http_status = null;
    protected $_credentials = array(
        'key' => null,
        'secret' => null
        );


    /**
     * Sets up a new SM instance
     *
     * @param array $credentials
     * @return void
     */
    public function __construct($credentials = array()) {
        $this->_credentials = $credentials;
    }


    /**
     * generic function that can handle any endpoint
     */
    public function call($path, $params = null) {
        $options = array('path'=>$path);
        if (!empty($params)) $options['params'] = $params;
        $res = self::rest($options);
        return $res;
    }


    /**
     * Generates authentication signature
     *
     * @param array $credentials
     * @return array
     */
    protected static function _generateSignature($credentials = null) {
        $ts = time();
        if (empty($credentials)) {
            $credentials = $this->_credentials;
        }
        $signature = base64_encode(hash_hmac('sha256', $ts, $credentials['secret'], true));

        return array('ts'=>$ts, 'signature'=>$signature);
    }


    /**
     * Generates a REST request to the API with retries and exponential backoff
     *
     * @param array $options
     * @param array $credentials
     * @return mixed
     */
    public function rest($options, $credentials = array()) {
        $defaults = array(
            'method' => 'POST',
            'url' => self::$apiUrl,
            'path' => '/',
            'query' => array()
            );

        $options = $options + $defaults;

        if (empty($credentials)) {
            $credentials = $this->_credentials;
        }

        if (!empty($options['params'])) {
            $params = htmlentities(json_encode($options['params']));
        }

        $auth = self::_generateSignature($credentials);
        $options['query'] = $options['query'] + array(
            'key' => $credentials['key'],
            'auth' => $auth['signature'],
            'ts' => $auth['ts'],
            'params' => (!empty($params)) ? $params : null,
            );

        $attempt = 0;
        while (true) {
            $attempt++;

            $curl = curl_init($options['url'] . $options['path']);
            curl_setopt_array($curl, array(
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => ($defaults['method'] == 'POST') ? true : false,
                CURLOPT_POSTFIELDS => ($defaults['method'] == 'POST') ? $options['query'] : http_build_query($options['query']),
                CURLOPT_USERAGENT => self::$userAgent .' '. self::VERSION
                ));

            $r = curl_exec($curl);
            $this->_http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($error = curl_error($curl)) {
                trigger_error('SMapi: curl error: ' . curl_error($curl), E_USER_WARNING);
                if (!self::_exponentialBackoff($attempt, self::$retries)) {
                    return false;
                }
                continue;
            }
            break;
        }

        return call_user_func_array(self::$serializer[1], array($r, true));
    }

    /**
     * Return the last HTTP status code received. Useful for debugging purposes.
     *
     * @return integer
     */
    public function httpStatus() {
        return $this->_http_status;
    }


    /**
     * Implements exponential backoff
     *
     * @param integer $current
     * @param integer $max
     * @return boolean
     */
    protected static function _exponentialBackoff($current, $max) {
        if ($current <= $max) {
            $delay = (int)(pow(2, $current) * 100000);
            usleep($delay);
            return true;
        }
        return false;
    }

}
