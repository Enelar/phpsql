<?php

class phpsql
{
  public $con_params;
  public function Connect( $connection, $params = NULL )
  {
    /* $connection
     *   scheme://user:pass@addr:port
     * OR
     *   ['scheme' => , 'user' => , 'pass' => , 'addr' => , 'port' => , 'db' => ]
     * $params
     *   key1:value1,key2:value2
     * OR
     *   ['key1' => 'value1', 'key2' => 'value2']
     */

    $this->con_params = $this->SplitConnectionString($connection);
    $connector = $this->GetConnector($this->con_params['scheme'], $this->con_params);
    return $connector;
  }

  private function SplitConnectionString( $str )
  {
    if (!is_string($str))
      return $str;

    $last_error_level = error_reporting(0);

    list($scheme, $other) = explode("://", $str);
    list($creditals, $other) = explode("@", $other);
    if (is_null($other))
    {
      $other = $creditals;
      $creditals = "";
    };

    list($user, $pass) = explode(":", $creditals);
    list($address, $database, $params) = explode("/", $other);
    list($ip, $port) = explode(":", $address);

    $res = [];
    foreach (explode(",", $params) as $pair)
    {
      list($k, $v) = explode(":", $pair);
      $res[$k] = $v;
    }

    error_reporting($last_error_level);

    return
    [
      'origin' => $str,
      'scheme' => $scheme,
      'user' => $user,
      'pass' => $pass,
      'addr' => $ip,
      'port' => $port,
      'db' => $database,
      'params' => $res
    ];
  }

  private static $supported_schemes = [];

  public static function RegisterSchemeHandler( $scheme, $classname )
  {
    $ref = &self::$supported_schemes;
    if (!isset($ref[$scheme]))
      $ref[$scheme] = $classname;
  }

  private function GetConnector( $scheme, $a )
  {
    $ref = &self::$supported_schemes;
    if (!isset($ref[$scheme]))
      die("Scheme {$scheme} not found");
    if (!is_string($ref[$scheme]))
      die("Scheme {$scheme} invalid");

    $classname = $ref[$scheme];
    $connector = new $classname();

    include_once('proxy.php');
    $proxy = new phpsql\proxy($connector);

    $proxy->OpenConnection($a['user'], $a['pass'], $a['addr'], $a['port'], $a['db'], $a['params']);

    return $proxy;
  }
}
