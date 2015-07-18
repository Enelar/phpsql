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
}


if (!class_alias("\phpa2o\phpa2o", "\\phpsql\\utils\\row_wrapper"))
  die("PHPA2O is required. Try run composer install?");