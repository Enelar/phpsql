<?php namespace phpsql;

abstract class connector_interface
{
  // On every call open new connection
  abstract public function OpenConnection( $user, $pass, $ip, $port, $db, $options );
  // On every call request server, block/wait for answer, and result it as associative array
  abstract public function Query( $query, $params );
  // Begin transaction
  abstract public function Begin();
  // Begin nested transaction
  abstract public function SaveStep( $name );
  // Rollback to previous saved step. (At least one exsist)
  abstract public function StepBack( $name );
  // You may free memory about step
  abstract public function ForgetStep( $name );
  // Rollback transaction
  abstract public function Rollback();
  // Commit transaction
  abstract public function Commit();
  // Request database, is current connection in transaction state
  abstract public function InTransaction();
  // Return raw connection object
  abstract public function RawConnection();
}

