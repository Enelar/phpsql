<?php namespace phpsql\connectors;

include_once('interface.php');

class pgsql extends \phpsql\connector_interface
{
  private $db;

  public function OpenConnection( $user, $pass, $ip, $port, $db, $options )
  {
    $str = "";
    if ($user) $str .= "user={$user} ";
    if ($pass) $str .= "password={$pass} ";
    if ($ip) $str .= "host={$ip} ";
    if ($port) $str .= "port={$port} ";
    if ($db) $str .= "dbname={$db} ";

    $this->db = pg_connect($str);
    return !!$this->db;
  }

  public function Query( $q, $p = [] )
  {
    foreach ($p as &$param)
      if (is_array($param))
        $param = array_php2pg($param, null);

    $res = pg_query_params($this->db, $q, $p);

    assert(!is_string($res), (string)$res);

    if ($res === false)
      return pg_last_error();

    $ret = [];

    while (($row = pg_fetch_assoc($res)) != false)
      $ret[] = array_recursive_extract($row);

    pg_free_result($res);

    return $ret;
  }

  public function Begin()
  {
    $this->Query("BEGIN;");
  }

  public function SaveStep( $name )
  {
    $transactions_statuses =
    [
      PGSQL_TRANSACTION_ACTIVE,
      PGSQL_TRANSACTION_INTRANS,
    ];

    $status = in_array(pg_transaction_status($this->db), $transactions_statuses);
    $this->Query("SAVEPOINT nested_transaction_{$name};");

    return $status;
  }

  public function StepBack( $name )
  {
    $this->Query("ROLLBACK TO SAVEPOINT nested_transaction_{$name};");
    return false;
  }

  public function ForgetStep( $name )
  {
    $this->Query("RELEASE SAVEPOINT nested_transaction_{$name};");
    return false;
  }

  public function Rollback()
  {
    $this->Query("ROLLBACK;");
    return false;
  }

  public function Commit()
  {
    $commit_failures =
    [
      PGSQL_TRANSACTION_ACTIVE,
      PGSQL_TRANSACTION_INTRANS,
    ];

    $status = in_array(pg_transaction_status($this->db), $commit_failures);
    $this->Query("COMMIT;");

    return $status;
  }

  public function InTransaction()
  {
    $transactions_statuses =
    [
      PGSQL_TRANSACTION_ACTIVE,
      PGSQL_TRANSACTION_INTRANS,
      PGSQL_TRANSACTION_INERROR,
    ];

    $stat = pg_transaction_status($this->db);
    return in_array($stat, $transactions_statuses);
  }

  public function RawConnection()
  {
    return $this->db;
  }
}

include_once('phpsql.php');
\phpsql\phpsql::RegisterSchemeHandler("pgsql", "\phpsql\connectors\pgsql");

function array_recursive_extract($obj)
{
  $ret = [];

  foreach ($obj as $key => $row)
  {
    if (is_array($row))
      $ret[$key] = array_recursive_extract($row);
    else if (isset($row[0]) && in_array($row[0], ['{'])) // expect postgresql array
    {
      $json = json_decode($row, true);
      if (!is_null($json))
        $ret[$key] = $json;
      else
      {
        $arr = array_pg2php($row);
        $ret[$key] = $arr;
      }
    }
    else
      if ($row == 't' || $row == 'f')
        $ret[$key] = $row == 't';
      else if (is_numeric($row))
        $ret[$key] = $row + 0;
      else
        $ret[$key] = $row;
  }

  return $ret;
}

function RecursiveParse($text)
{
  if (is_array($text))
  {
    $ret = [];
    foreach ($text as $row)
      $ret[] = RecursiveParse($row);
    return $ret;
  }


  $split = explode(',', $text);

  $values = [];

  $brackets = null;
  $quotes = [];
  $scope = &$brackets;

  foreach ($split as $value)
  {
    if (count($quotes) || $value[0] == '"')
      $quotes[] = $value;
    if (count($quotes))
    {
      if ($value == '"')
        continue;
      if (substr($value, -2) == '\\"')
        continue;

      // Simple string ending
      if (substr($value, -1) == '"')
      {
        $value = substr(implode(',', $quotes), 1, -1); // trim "
        $quotes = [];

        if ($brackets !== null)
          $scope[] = $value;
        else
          $values[] = $value;
        // Ignore any opcode in simple string
        continue;
      }
      if (!preg_match('/^(.*[^\\\])\"(}+)$/', $value, $match))
      {
        // Just quote chunk
        continue;
      }


      // End of string with } opcodes, continue execution
      $value = implode(',', $quotes); // trim "
      $quotes = [];
      // value now "string"}}} format
    }

    if ($value[0] == '{')
    {
      $cut = '{' . $value;


      while (strlen($cut) > 1)
      {
        if ($cut[0] != '{')
          break;

        $cut = substr($cut, 1);

        if ($cut[0] != '{')
        {
          if ($cut[0] == '"')
          {
            $inline_quote = preg_match('/^\"(.*[^\\\])\"(}*)$/', $cut, $match);

            if (!$inline_quote)
            {
              $quotes[] = $cut;
              continue;
            }
            else
              $scope[] = $match[1] . $match[2];
          }
          else
            $scope[] = $cut;

        }

        else
        {
          if ($scope === null)
          {
            $scope = [];
            continue;
          }


          $scope[] = [];


          end($scope);
          $saved = &$scope[key($scope)];

          unset($scope);
          $scope = &$saved;

        }
      }

      if (count($quotes))
        continue;
    }
    else if (count($scope))
      $scope[] = $value;


    if (count($brackets))
    {
      if (substr($value, -1) == '}')
      {
        $cut = $value;
        while (strlen($cut) > 1)
        {
          $ch = substr($cut, -1);
          $cut = substr($cut, 0, -1);

          if ($ch != '}')
            break;

          end($scope);
          $last = &$scope[key($scope)];
          $last = substr($last, 0, -1);
          if (preg_match('/^\"(.*)\"(}*)$/', $last, $match))
            $last = $match[1] . $match[2];

          if ($scope === $brackets)
          {
            $values[] = $brackets;
            $scope = null;
            break;
          }


          $saved_stack = $scope;
          $scope = null;

          $latest_scope = &$brackets;
          while (is_array(end($latest_scope)))
          {
            $php_wtf = &$latest_scope[key($latest_scope)];
            unset($latest_scope);
            $latest_scope = &$php_wtf;
          }

          if ($scope !== $brackets)
          {
            $scope = '{' . implode(',', $saved_stack) . '}';
            unset($scope);
            $scope = &$latest_scope;
          }
        }

        //if ($brackets !== $scope && $scope !== null)
          //continue;

        continue;

        //$scope = null;
      }

      continue;
    }

    $values[] = $value;
  }


  if (!is_null($brackets))
    $values[] = $brackets;

  if (is_string($values[0]))
    $return = $values[0];
  else
    $return = RecursiveParse($values[0]);

  return $return;
}

function array_pg2php($text)
{
  if(is_null($text))
    return [];
  if(!is_string($text) || $text == '{}')
    return [];

  return RecursiveParse($text);
}

// http://www.youlikeprogramming.com/2013/01/interfacing-postgresqls-hstore-with-php/
function array_php2pg($array, $data_type = 'character varying')
{
  $array = (array) $array; // Type cast to array.
  $result = [];

  foreach ($array as $entry)
  { // Iterate through array.
    if (is_array($entry)) // Supports nested arrays.
    {
      $result[] = array_php2pg($entry, $data_type);
      continue;
    }

    $escaped_quotes = str_replace('"', '\"', $entry);
    $escaped_entry = pg_escape_string($escaped_quotes); // Escape everything else.
    if ($escaped_entry != $entry || strpos($escaped_entry, ',') !== false)
      $escaped_entry = "\"{$escaped_entry}\"";

    $result[] = $escaped_entry;
  }

  $ret = '{' . implode(',', $result) . '}';

  if ($data_type !== null)
    $ret .= '::' . $data_type . '[]'; // format
  return $ret;
}
