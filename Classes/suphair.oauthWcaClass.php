<?php

namespace Suphair;

class OauthWca {

    protected static $scope = 'public';
    protected static $clientId;
    protected static $urlRefer;
    protected static $clientSecret;
    protected static $connection;

    const VERSION = '1.0.0';

    private function __construct() {
        
    }

    public static function getInstance() {
        if (self::$_instance === null) {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    private function __clone() {
        
    }

    private function __wakeup() {
        
    }

    static function set($clientId, $clientSecret, $scope, $urlRefer, $connection) {
        self::$scope = $scope;
        self::$clientId = $clientId;
        self::$clientSecret = $clientSecret;
        $http = filter_input(INPUT_SERVER, 'SERVER_NAME') == 'localhost' ? "http" : "https";
        self::$urlRefer = $http . ':' . $urlRefer;
        self::$connection = $connection;
    }

    static function url() {
        $_SESSION['suphair.oauth.request_uri'] = filter_input(INPUT_SERVER, 'REQUEST_URI');

        return "https://www.worldcubeassociation.org/oauth/authorize?"
                . "client_id=" . self::$clientId . "&"
                . "redirect_uri=" . urlencode(self::$urlRefer) . "&"
                . "response_type=code&"
                . "scope=" . self::$scope . "";
    }

    static function location() {
        header("Location: {$_SESSION['suphair.oauth.request_uri']}");
        exit();
    }

    static function authorize() {

        if (filter_input(INPUT_GET, 'error') == 'access_denied') {
            self::location();
        }

        $code = filter_input(INPUT_GET, 'code');
        if ($code) {
            $postdata = http_build_query(
                    [
                        'grant_type' => 'authorization_code',
                        'client_id' => self::$clientId,
                        'client_secret' => self::$clientSecret,
                        'code' => $code,
                        'redirect_uri' => self::$urlRefer
                    ]
            );

            $opts = array('http' =>
                array(
                    'method' => 'POST',
                    'header' => 'Content-type: application/x-www-form-urlencoded',
                    'content' => $postdata
                )
            );
            $context = stream_context_create($opts);
            $token = file_get_contents("https://www.worldcubeassociation.org/oauth/token", false, $context);
            $accessToken = json_decode($token)->access_token;

            if (!$accessToken) {
                self::location();
            }

            $ch = curl_init('https://www.worldcubeassociation.org/api/v0/me');
            $authorization = "Authorization: Bearer $accessToken";
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $authorization));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);

            if (isset(json_decode($result)->me->id)) {
                $me = json_decode($result)->me;
                self::log($me);
                return $me;
            } else {
                self::location();
            }
        }
    }

    private static function log($me) {

        if (isset($me->id) and is_numeric($me->id)) {
            $id_escape = mysqli_real_escape_string(
                    self::$connection, $me->id
            );
        } else {
            return;
        }
        if (isset($me->name)) {
            $name_escape = mysqli_real_escape_string(
                    self::$connection, $me->name
            );
        } else {
            $name_escape = FALSE;
        }

        if (isset($me->wca_id)) {
            $wcaid_escape = mysqli_real_escape_string(
                    self::$connection, $me->wca_id
            );
        } else {
            $wcaid_escape = FALSE;
        }
        if (isset($me->country_iso2)) {
            $countryiso2_escape = mysqli_real_escape_string(
                    self::$connection, $me->country_iso2
            );
        } else {
            $countryiso2_escape = FALSE;
        }
        $query = " INSERT INTO oauthwca_logs "
                . "(`me_id`,`me_name`,`me_wcaid`,`me_countryiso2`,`version`) "
                . "VALUES"
                . "('$id_escape',"
                . "'$name_escape',"
                . "'$wcaid_escape',"
                . "'$countryiso2_escape',"
                . "'" . self::VERSION . "')";

        mysqli_query(self::$connection, $query);
        return mysqli_insert_id(self::$connection);
    }

    public static function init($connection) {
        $queries = [];
        $errors = [];

        $queries['oauthwca_logs'] = "
            CREATE TABLE `oauthwca_logs` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `me_id` int(11) DEFAULT NULL,
                `me_name` varchar(255) DEFAULT NULL,
                `me_wcaid` varchar(10) DEFAULT NULL,
                `me_countryiso2` varchar(2) DEFAULT NULL,
                `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `version` varchar(11) DEFAULT NULL,
                PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ";

        foreach ($queries as $table => $query) {
            if (!mysqli_query($connection, $query)) {
                $errors[$table] = mysqli_error($connection);
            }
        }

        if (sizeof($errors)) {
            trigger_error("oauthwca.createTables: " . json_encode($errors), E_USER_ERROR);
        }
    }

}