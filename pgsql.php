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

  public function Query( $q, $p = [] )
  {
    foreach ($p as &$param)
      if (is_array($param))
        $param = pgArrayFromPhp($param, null);

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
\phpsql::RegisterSchemeHandler("pgsql", "\phpsql\connectors\pgsql");

// http://www.youlikeprogramming.com/2013/01/interfacing-postgresqls-hstore-with-php/ 
function pgArrayFromPhp($array, $data_type = 'character varying')
{
  $array = (array) $array; // Type cast to array.
  $result = [];
  
  foreach ($array as $entry)
  { // Iterate through array.
    if (is_array($entry)) // Supports nested arrays.
    {
      $result[] = pgArrayFromPhp($entry, $data_type);
      continue;
    }
    
    $entry = str_replace('"', '\\"', $entry); // Escape double-quotes.
    $entry = pg_escape_string($entry); // Escape everything else.

    if (is_numeric($entry))
      $result[] = $entry;
    else
      $result[] = "'" . $entry . "'";
  }
 
  $ret = '{' . implode(',', $result) . '}';

  if ($data_type !== null)
    $ret .= '::' . $data_type . '[]'; // format
  return $ret;
}