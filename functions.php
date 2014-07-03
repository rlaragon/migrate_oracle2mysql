<?php
function getDBFormat($param) {
    return iconv('UTF-8', 'WINDOWS-1252', $param);
}

function connect_mysql() {
  $dsn = 'mysql:host=<hostip/hostname>;dbname=<dbname>';
  $username = '<user>';
  $password = '<password>';
  $options = array(
      PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
  ); 
  try {
    $dbh = new PDO($dsn, $username, $password, $options);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $dbh;
  }
  catch (PDOException $e) {
    print $e->getMessage();
    return 'ERROR '.$e->getMessage();
  }
}

function connect_oracle() {
    global $conn;
    $conn = oci_connect('<oracle_user>', '<oracle_pass>', '<oracle_server>/<oracle listener>', 'WE8MSWIN1252');
    if (!$conn) {
        $e = oci_error();
        trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
    }
}

function getParam($param) {
       $lparam = iconv('WINDOWS-1252', 'UTF-8', $param);
       $lparam = $lparam == null ? "" : $lparam;
       return $lparam;
}


?>
