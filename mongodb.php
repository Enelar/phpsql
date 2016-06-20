<?php namespace phpsql\connectors;

include_once('interface.php');

class mongodb extends \phpsql\connector_interface
{
  private $db;

  public function OpenConnection( $user, $pass, $ip, $port, $db, $options )
  {
    $str = "mongodb://";
    if ($user) $str .= "{$user}";
    if ($pass) $str .= ":{$pass}";
    if ($ip && $user) $str .= "@";
    if ($ip) $str .= "{$ip}";
    if ($port) $str .= ":{$port}";

    $this->db = new \MongoClient($str);

    if ($db)
      $this->db = $this->db->$db;

    return !!$this->db;
  }

  public function Query( $q, $p = [] )
  {
    foreach ($p as &$param)
      if (is_array($param))
        $param = array_php2pg($param, null);

    $res = pg_query_params($this->db, $q, $p);

    assert(!is_string($res), $res);

    if ($res === false)
      return pg_last_error();

    $ret = [];

    while (($row = pg_fetch_assoc($res)) != false)
      $ret[] = array_recursive_extract($row);

    pg_free_result($res);

    return $ret;
  }

  public function Begin()
  {
    $this->Query("BEGIN;");
  }

  public function SaveStep( $name )
  {
    $this->Query("SAVEPOINT nested_transaction_{$name};");
  }

  public function StepBack( $name )
  {
    $this->Query("ROLLBACK TO SAVEPOINT nested_transaction_{$name};");
  }

  public function ForgetStep( $name )
  {
    $this->Query("RELEASE SAVEPOINT nested_transaction_{$name};");
  }

  public function Rollback()
  {
    $this->Query("ROLLBACK;");
  }

  public function Commit()
  {
    $this->Query("COMMIT;");
  }

  public function InTransaction()
  {
    $stat = pg_transaction_status($this->db);
    return $stat === PGSQL_TRANSACTION_ACTIVE || $stat === PGSQL_TRANSACTION_INTRANS || $stat === PGSQL_TRANSACTION_INERROR;
  }

  public function RawConnection()
  {
    return $this->db;
  }
}

include_once('phpsql.php');
\phpsql::RegisterSchemeHandler("mongodb", "\phpsql\connectors\mongodb");
