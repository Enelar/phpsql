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

    if (!$db)
      throw new \Exception("MongoDB database should be specified");

    $con = new \MongoClient($str);

    if ($con && $db)
      $this->db = $con->selectDB($db);

    return !!$this->db;
  }

  public function Query( $q, $p = [] )
  {
    $table = new \MongoCollection($this->db, $q);
    $res = $table->find($p);

    $ret = [];
    foreach ($res as $row)
      $ret[] = $row;

    return $ret;
  }

  public function Begin()
  {
    throw new \Exception("Unimplemented");
  }

  public function SaveStep( $name )
  {
    throw new \Exception("Unimplemented");
  }

  public function StepBack( $name )
  {
    throw new \Exception("Unimplemented");
  }

  public function ForgetStep( $name )
  {
    throw new \Exception("Unimplemented");
  }

  public function Rollback()
  {
    throw new \Exception("Unimplemented");
  }

  public function Commit()
  {
    throw new \Exception("Unimplemented");
  }

  public function InTransaction()
  {
    throw new \Exception("Unimplemented");
  }

  public function RawConnection()
  {
    return $this->db;
  }
}

include_once('phpsql.php');
\phpsql::RegisterSchemeHandler("mongodb", "\phpsql\connectors\mongodb");
