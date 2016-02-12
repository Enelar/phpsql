<?php namespace phpsql;

require_once('phpsqldb.php');

function OneLineConfig($conf)
{
  require_once('phpsql.php');
  require_once('wrapper.php');

  list($scheme) = explode(":", $conf);
  require_once("{$scheme}.php");

  $phpsql = new \phpsql();
  $connection = $phpsql->Connect($conf);
  \db::Bind(new \phpsql\utils\wrapper($connection));
}
