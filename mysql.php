<?php namespace phpsql\connectors;

include_once('interface.php');

class mysql extends \phpsql\connector_interface
{
  private $db;

  public function OpenConnection( $user, $pass, $ip, $port, $db, $options )
  {
    $str = [];

    if ($ip)    $str[] = "host={$ip}";
    if ($port)  $str[] = "port={$port}";
    if ($db)    $str[] = "dbname={$db}";

    try
    {
      $con = new \PDO('mysql:'.(implode(';', $str)), $user, $pass);
    } catch (\PDOException $e)
    {
      die($e->getMessage());
    }

    $this->db = $con;
    return true;
  }

  public function Query( $q, $p )
  {
    $stmt = $conn->prepare($q);
    $stmt->execute($p);

    $ret = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $stmt->closeCursor();
    unset($stmt);

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
    return !!$this->db->inTransaction();
  }
}

include_once('phpsql.php');
\phpsql::RegisterSchemeHandler("mysql", "\phpsql\connectors\mysql");
