<?php namespace phpsql\utils;

include("vendor/autoload.php");

class wrapper
{
  private $obj;

  public function __construct($obj)
  {
    $this->obj = $obj;
  }

  public function __call($method, $args)
  {
    return call_user_func_array([$this->obj, $method], $args);
  }

  public function Query($query, $params = [], $one_row = false, $reindex_by = null)
  {
    $res = $this->obj->Query($query, $params, $one_row, $reindex_by);

    if (is_string($res))
      return $res;

    $row_array_to_obj = function($row)
    {
      return new row_wraper($row);
    };

    return $row_array_to_obj($res);
  }

  public function SafeQuery($query, $params = [], $one_row = false, $reindex_by = null)
  {
    $res = $this->Query($query, $params, $one_row, $reindex_by);
    if (is_string($res))
      throw new \Exception($res);

    return $res;
  }
}

class row_wraper extends \phpa2o\phpa2o
{
}
