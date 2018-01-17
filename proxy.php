<?php namespace phpsql;

class proxy_storage
{
  protected $connector;

  public function __construct( $connector )
  {
    $this->connector = $connector;
  }
}

class proxy extends proxy_storage
{
  private $transactions = [];
  private $next_transaction_id = 1;
  private $last_insert_id = "NOT_AN_ID";

  public function OpenConnection( $user, $pass, $ip, $port, $db, $options )
  {
    return $this->connector->OpenConnection($user, $pass, $ip, $port, $db, $options);
  }

  public function AffectedID()
  {
    $affected_id = $this->connector->affected_id;

    if ($affected_id === null)
      throw new \Exception("Unable to determine affected ID. This DB support it?");

    if ($affected_id === false)
      throw new \Exception("Unable to determine affected ID. Maybe you updating multiply rows?");

    return $affected_id;
  }

  public function Query( $query, $params = [], $one_row = false, $reindex_by = null )
  {
    assert(is_array($params), "phpsql->Query params should be array");

    foreach ($params as &$param)
      if ($param instanceof \phpa2o\phpa2o)
        $param = $param->__2array();

    $res = $this->connector->Query($query, $params);

    if (!is_array($res))
      return $res;

    $ret = [];
    if (!is_null($reindex_by))
    {
      if (!$one_row)
        foreach ($res as $row)
          $ret[$row[$reindex_by]] = $row;
      else
        foreach ($res as $row)
          $ret[] = $row[$reindex_by];

      return $ret;
    }
    else
      $ret = $res;

    if ($one_row && count($ret) == 1)
      return $ret[0];
    return $ret;
  }

  public function Begin()
  {
    $id = $this->next_transaction_id++;
    $this->transactions[] = $id;
    if (!$this->InTransaction())
      $this->connector->Begin();
    else
      $this->connector->SaveStep($id);

    return new transaction_object($this, $id);
  }

  public function Rollback( $id )
  {
    $this->DieInWrongTransactionExitOrder($id);
    if ($this->IsHeadTransaction($id))
      $res = $this->connector->Rollback();
    else
    {
      $res = $this->connector->StepBack($id);
      $this->connector->ForgetStep($id);
    }
    array_pop($this->transactions);

    return is_null($res) ? false : $res;
  }

  public function Commit( $id )
  {
    $this->DieInWrongTransactionExitOrder($id);
    if ($this->IsHeadTransaction($id))
      $res = $this->connector->Commit();
    else
      $res = $this->connector->ForgetStep($id);
    array_pop($this->transactions);

    return is_null($res) ? true : $res;
  }

  private function DieInWrongTransactionExitOrder( $id )
  {
    $cur_transaction = end($this->transactions);
    if ($cur_transaction != $id)
      die("Could not exit from transaction {$id}, waiting to finish {$cur_transaction}");
  }

  public function InTransaction()
  {
    return $this->connector->InTransaction();
  }

  private function IsHeadTransaction( $id )
  {
    if (!count($this->transactions))
      return false;
    return $this->transactions[0] == $id;
  }

  public function RawConnection()
  {
    return $this->connector->RawConnection();
  }

  // Dig into transaction if required
  public function ConditionalQuery( $check, $then, $else )
  {
    if ($check())
      return $then();

    $tran = $this->Begin();

    if ($check()) // Appeared while we getting lock
      return $tran->Rollback();

    $ret = $else();

    $tran->Commit();
    return $ret;

    // Execution example:
    $this->ConditionalQuery
    (
      function()
      {
        return db::Query("SELECT count(*) FROM table WHERE id=1")['count'];
      },
      function ()
      {
        return db::Query("UPDATE table SET acc=acc+1 WHERE id=1");
      },
      function ()
      {
        return db::Query("INSERT INTO table(id) VALUES (1)");
      }
    );
  }
}

class transaction_object
{
  private $proxy;
  private $id;

  public function __construct( $proxy, $id )
  {
    $this->proxy = $proxy;
    $this->id = $id;
  }

  public function Commit()
  {
    return $this->proxy->Commit($this->id);
  }

  public function Rollback()
  {
    return $this->proxy->Rollback($this->id);
  }

  public function Finish( $status )
  {
    if ($status)
      return $this->Commit();
    return $this->Rollback();
  }
}
