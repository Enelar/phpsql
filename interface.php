<?php namespace phpsql;

function OverloadRequired()
{
  $trace = debug_backtrace();
  $caller = array_shift($trace);

  $a = $caller['function'];
  $b = $caller['class'] ? $caller['class'] : "";
  die("Overload '{$b}::{$a}' required") ;
}

class connector_interface
{
  public function OpenConnection( $user, $pass, $ip, $port, $db, $options )
  {
    // On every call open new connection
    OverloadRequired();
  }

  public function Query( $query, $params )
  {
    // On every call request server, block/wait for answer, and result it as associative array
    OverloadRequired();
  }

  public function Begin()
  {
    // Begin transaction
    OverloadRequired();
  }

  public function SaveStep( $name )
  {
    // Begin nested transaction
    OverloadRequired();
  }
  
  public function StepBack( $name )
  {
    // Rollback to previous saved step. (At least one exsist)
    OverloadRequired();
  }
  
  public function ForgetStep( $name )
  {
    // You may free memory about step
    OverloadRequired();
  }
  
  public function Rollback()
  {
    // Rollback transaction
    OverloadRequired();
  }
  
  public function Commit()
  {
    // Commit transaction
    OverloadRequired();
  }

  public function InTransaction()
  {
    // Request database, is current connection in transaction state
    OverloadRequired();
  }
}
