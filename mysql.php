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

  public function Query( $q, $p = [] )
  {
    $stmt = $this->db->prepare($q);
    $stmt->execute($p);

    $issue = $stmt->errorinfo();
    assert(is_null($issue[2]), $issue[2]);


    $ret = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // If we affect only one row, we could determine affected id
    $affected_rows = $stmt->rowCount();
    if ($affected_rows == 1 && strpos(strtolower($q), "select") == false)
      $this->affected_id = $this->db->lastInsertId();
    else
      $this->affected_id = false;

    $stmt->closeCursor();
    unset($stmt);

    return $ret;
  }

  public function Begin()
  {
    $this->Query("-- BEGIN;");
    $this->db->beginTransaction();
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
    $this->Query("-- ROLLBACK;");
    $this->db->rollBack();
  }

  public function Commit()
  {
    $this->Query("-- COMMIT;");
    $this->db->commit();
  }

  public function InTransaction()
  {
    return !!$this->db->inTransaction();
  }

  public function RawConnection()
  {
    return $this->db;
  }
}

include_once('phpsql.php');
\phpsql::RegisterSchemeHandler("mysql", "\phpsql\connectors\mysql");
