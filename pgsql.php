<?php namespace phpsql\connectors;

include_once('interface.php');

class pgsql extends \phpsql\connector_interface
{
  private $db;

  public function OpenConnection( $user, $pass, $ip, $port, $db, $options )
  {
    $str = "";
    if ($user) $str .= "user={$user} ";
    if ($pass) $str .= "password={$pass} ";
    if ($ip) $str .= "host={$ip} ";
    if ($port) $str .= "port={$port} ";
    if ($db) $str .= "dbname={$db} ";

    $this->db = pg_connect($str);
    return !!$this->db;
  }

  public function Query( $q, $p )
  {
    $res = pg_query_params($this->db, $q, $p);

    if (is_string($res))
      assert(false, $res);

    $ret = [];

    while (($row = pg_fetch_assoc($res)) != false)
      $ret[] = $row;

    return $ret;
  }

  public function Begin()
  {
    $this->Query("BEGIN;");
  }

  public function SaveStep( $name )
  {
    $this->Query("SAVEPOINT {$name};");
  }
  
  public function StepBack( $name )
  {
    $this->Query("ROLLBACK TO SAVEPOINT {$name};");
  }
  
  public function ForgetStep( $name )
  {
    $this->Query("RELEASE SAVEPOINT {$this->name};");
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
}

include_once('phpsql.php');
\phpsql::RegisterSchemeHandler("pgsql", "\phpsql\connectors\pgsql");
