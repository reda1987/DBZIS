<?php

// $conn     = oci_connect('manten', 'manten123', '192.168.1.160:1521/ORCL', 'AL32UTF8', OCI_DEFAULT);
$username = "accumed";
$host     = "192.168.1.160";
$port     = 1521;
$dbname   = "MANTEN";
$password = "accumed";
$connStr  = "(DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = " . $host . ")(PORT = " . $port . ")) (CONNECT_DATA = (SERVICE_NAME = ORCL) (SID = ORCL)))";

// url = username@host/db_name

$dburl = $username . "@" . $host . "/" . $dbname;
var_dump($connStr);
$conn = oci_connect($username, $password, $connStr);
if (!$conn) {
    echo "error in connection" . PHP_EOL;
    $e = oci_error();
    trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
    echo PHP_EOL;
    var_dump($e['message']);
    echo PHP_EOL;
    var_dump($conn);
    echo PHP_EOL;

}
