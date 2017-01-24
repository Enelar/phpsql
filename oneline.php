<?php namespace phpsql;

require_once(__DIR__.'/db.php');

function OneLineConfig($conf)
{
  require_once(__DIR__.'/phpsql.php');
  require_once(__DIR__.'/wrapper.php');

  list($scheme) = explode(":", $conf);
  require_once(__DIR__."/{$scheme}.php");

  $phpsql = new \phpsql\phpsql();
  $connection = $phpsql->Connect($conf);
  \db::Bind(new \phpsql\utils\wrapper($connection));
}
