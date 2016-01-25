<?php namespace phpsql;

require('db.php');

function OneLineConfig($conf)
{
  require('phpsql.php');
  require('wrapper.php');

  list($scheme) = explode(":", $conf);
  require("{$scheme}.php");

  $phpsql = new \phpsql();
  $connection = $phpsql->Connect($conf);
  \db::Bind(new \phpsql\utils\wrapper($connection));
}
