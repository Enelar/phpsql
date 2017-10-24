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
    $this->Query("SAVEPOINT nested_transaction_{$name};");
  }

  public function StepBack( $name )
  {
    $this->Query("ROLLBACK TO SAVEPOINT nested_transaction_{$name};");
  }

  public function ForgetStep( $name )
  {
    $this->Query("RELEASE SAVEPOINT nested_transaction_{$name};");
  }

  public function Rollback()
  {
    $this->Query("ROLLBACK;");
  }

  public function Commit()
  {
    $this->Query("COMMIT;");
  }

  public function InTransaction()
  {
    $stat = pg_transaction_status($this->db);
    return $stat === PGSQL_TRANSACTION_ACTIVE || $stat === PGSQL_TRANSACTION_INTRANS || $stat === PGSQL_TRANSACTION_INERROR;
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

  error_log(json_encode($obj));
  foreach ($obj as $key => $row)
  {
    if (is_array($row))
      $ret[$key] = array_recursive_extract($row);
    else if (isset($row[0]) && in_array($row[0], ['{', '['])) // expect postgresql array
    {
      var_dump($row, $json);
      if (!is_null($json))
        $ret[$key] = $json;
      else
      {
        var_dump($row);
        $ret[$key] = array_pg2php($row);
        var_dump($arr);
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


  echo "START";
  var_dump($text);
  $split = explode(',', $text);

  $values = [];

  $brackets = null;
  $quotes = [];
  $scope = &$brackets;

  var_dump($split);
  foreach ($split as $value)
  {
    var_dump(["ITER", "values" => $values, "value" => $value, "brackets" => $brackets, "quotes" => $quotes, "scope" => $scope]);
    if (count($quotes) || $value[0] == '"')
      $quotes[] = $value;
var_dump("RILI", count($quotes));
    if (count($quotes))
    {
      if ($value == '"')
        continue;
      if (substr($value, -1) != '"')
        continue;
      if (substr($value, -2) == '\\"')
        continue;
      // End of string

      $value = implode(',', $quotes);
      $quotes = [];

      if ($brackets !== null)
      {
        $scope[] = $value;
        continue;
      }
    }

    if ($value[0] == '{')
    {
      $cut = '{' . $value;

      var_dump(["CUT", $cut]);

      while (strlen($cut) > 1)
      {
        if ($cut[0] != '{')
          break;

        $cut = substr($cut, 1);

        if ($cut[0] != '{')
        {
          echo "PUT";
          echo $cut;
          var_dump($values, $scope);
          if ($cut[0] == '"')
          {
            echo "wat";
            var_dump($scope);
            if (!preg_match('/^\"(.*)\"}*$/', $cut, $match))
            {
              $quotes[] = $cut;
              echo "quotes";
              continue;
            }
            else
              $scope[] = $cut;
            var_dump($scope, $match);
          }
          else
            $scope[] = $cut;

          echo "DONE";
        }

        else
        {
          var_dump($scope);
          if ($scope === null)
          {
            $scope = [];
            continue;
          }

          echo "EXTRA";

          $scope[] = [];
          var_dump($brackets);


          end($scope);
          $saved = &$scope[key($scope)];

          unset($scope);
          $scope = &$saved;

          var_dump($brackets);
        }
      }

      if (count($quotes))
        continue;
    }
    else if (count($scope))
      $scope[] = $value;

    var_dump($brackets);

    if (count($brackets))
    {
      if (substr($value, -1) == '}')
      {
        $cut = $value;
        var_dump($value);
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
            echo "FOLDING";
            $values[] = $brackets;
            $scope = null;
            break;
          }

          var_dump(['add', $brackets, $scope]);

          $saved_stack = $scope;
          $scope = null;
          var_dump($brackets);

          $latest_scope = &$brackets;
          while (is_array(end($latest_scope)))
          {
            var_dump(["wtf", end($latest_scope)]);

            $php_wtf = &$latest_scope[key($latest_scope)];
            unset($latest_scope);
            $latest_scope = &$php_wtf;
          }

          var_dump(["pre", $brackets, $saved_stack, $latest_scope]);


        //  $pointer = &$latest_scope[key($latest_scope)];

          if ($scope !== $brackets)
          {
            $scope = '{' . implode(',', $saved_stack) . '}';
            unset($scope);
            $scope = &$latest_scope;
          }
          else
          {
            var_dump($saved_stack);
          }

          var_dump([$brackets, $saved_stack, $latest_scope]);
          var_dump(["res", $brackets, $scope]);


        }

        if ($brackets !== $scope && $scope !== null)
          continue;

        echo "TODO FOLD TO VALUES";
        var_dump($brackets);

        var_dump($values);
        continue;
        var_dump($scope);

        $scope = null;
      }

      continue;
    }

    $values[] = $value;
  }

  echo "RETURNING";
  var_dump($values);

  if (is_string($values[0]))
    $return = $values[0];
  else
    $return = RecursiveParse($values[0]);
  var_dump($return);

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

    $entry = pg_escape_string($entry); // Escape everything else.

    $result[] = $entry;
  }

  $ret = '{' . implode(',', $result) . '}';

  if ($data_type !== null)
    $ret .= '::' . $data_type . '[]'; // format
  return $ret;
}
