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

  public function OpenConnection( $user, $pass, $ip, $port, $db, $options )
  {
    return $this->connector->OpenConnection($user, $pass, $ip, $port, $db, $options);
  }

  public function Query( $query, $params = [], $one_row = false, $reindex_by = null )
  {
    assert(is_array($params), "phpsql->Query params should be array");

    $res = $this->connector->Query($query, $params);

    $ret = [];
    if (!is_null($reindex_by))
      foreach ($res as $row)
        $ret[$row[$reindex_by]] = $row[$reindex_by];
    else
      $ret = $res;

    if ($one_row && count($ret) == 1)
      return $ret[0];
    return $ret;
  }

  public function Begin()
  {
    $id = $this->$next_transaction_id++;
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
    if (!$this->InTransaction())
      $this->connector->Rollback();
    else
    {
      $this->connector->StepBack($id);
      $this->connector->ForgetStep($id);
    }
    return false;
  }
  
  public function Commit( $id )
  {
    $this->DieInWrongTransactionExitOrder($id);
    if (!$this->InTransaction())
      $this->connector->Commit();
    else
      $this->connector->ForgetStep($id);
    return true;
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
    return $this->proxy->Commit($id);
  }
  
  public function Rollback()
  {
    return $this->proxy->Rollback($id);
  }
  
  public function Finish( $status )
  {
    if ($status)
      return $this->Commit();
    return $this->Rollback();
  }
}
