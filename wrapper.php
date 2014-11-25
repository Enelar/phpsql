<?php namespace phpsql\utils;

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

    $row_array_to_obj = function($row)
    {
      return new row_wraper($row);
    };

    return $row_array_to_obj($res);
  }
}

class row_wraper implements \arrayaccess, \JsonSerializable, \iterator
{
  private $original_row_array;
  
  public function __construct(&$row)
  {
    phoxy_protected_assert(is_array($row), "Row wrapper support only arrays");
    $this->original_row_array = $row;
  }

  public function __invoke()
  {
    return count($this->original_row_array);
  }

  public function __set ( $name , $value )
  {
    $this->offsetSet($name, $value);
    return $this->__get($name);
  }

  public function __get ( $name )
  {
    return $this->offsetGet($name);
  }

  public function offsetExists ( $name )
  {
    $o = &$this->original_row_array;
    return isset($o[$name]);
  }
  
  public function offsetGet ( $name )
  {    
    if (!$this->offsetExists($name))
      return null;

    $o = &$this->original_row_array;
    return $this->RemainWrapper($o[$name]);
  }
  
  public function offsetSet($name, $value)
  {
      $o = &$this->original_row_array;
      if (is_null($name))
          $o[] = $value;
      else
          $o[$name] = $value;
  }
  
  public function offsetUnset ( $name )
  {
      unset($this->original_row_array[$name]);
  }

  public function jsonSerialize()
  {
      return $this->original_row_array;
  }

  public function rewind()
  {
      reset($this->original_row_array);
  }

  public function current()
  {
      return $this->RemainWrapper(current($this->original_row_array));
  }

  public function key()
  {
      return $this->RemainWrapper(key($this->original_row_array));
  }

  public function next()
  {
      return $this->RemainWrapper(next($this->original_row_array));
  }

  public function valid()
  {
      return false !== $this->current();
  }

  private function RemainWrapper(&$data)
  {
      if (!is_array($data))
        return $data;
      return new row_wraper($data);
  }
}