<?php
// Compatibility with https://github.com/Enelar/pgsql_php
class db
{
  public static function __callStatic( $name, $args )
  {
    return call_user_func_array(array(self::$db, $name), $args);
  }

  private static $db;

  public static function Bind( $obj )
  {
    self::$db = $obj;
  }
}
