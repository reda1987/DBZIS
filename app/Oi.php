<?php

// export NLS_LANG=AMERICAN_AMERICA.AL32UTF8
// export NLS_LANG=AMERICAN_AMERICA.AR8MSWIN1256

function oiConnect($connectionData, $encoding = "AL32UTF8")
{
    echo PHP_EOL;
    echo "Oracle Connection" . PHP_EOL;
    if (isset($connectionData['SERVICE_NAME'])) {
        $connStr = "(DESCRIPTION=(ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = " . $connectionData['host'] . ")(PORT = " . $connectionData['port'] . ")))(CONNECT_DATA=(SERVICE_NAME=" . $connectionData['SERVICE_NAME'] . ")))";
    } else {
        $connStr = "(DESCRIPTION=(ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = " . $connectionData['host'] . ")(PORT = " . $connectionData['port'] . ")))(CONNECT_DATA=(SID=ORCL)))";
    }

    $conn = oci_connect($connectionData['username'], $connectionData['password'], $connStr, $encoding);

    if (!$conn) {
        echo $connStr;
        echo PHP_EOL;
        if (isset($connectionData['SERVICE_NAME'])) {
            $connStr = "(
        DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = " . $connectionData['host'] . ")(PORT = " . $connectionData['port'] . "))(CONNECT_DATA = (SERVICE_NAME = " . $connectionData['SERVICE_NAME'] . ") (SID = " . $connectionData['sid'] . ")))";
        } else {
            $connStr = "(
        DESCRIPTION = (ADDRESS = (PROTOCOL = TCP)(HOST = " . $connectionData['host'] . ")(PORT = " . $connectionData['port'] . "))(CONNECT_DATA = (SERVICE_NAME = ORCL) (SID = " . $connectionData['sid'] . ")))";
        }

        $conn = oci_connect($connectionData['username'], $connectionData['password'], $connStr, $encoding);
        if (!$conn) {
            echo "error in connection" . PHP_EOL;
            $e = oci_error();
            trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
            var_dump($connectionData);
            echo PHP_EOL;
            var_dump($e['message']);
            echo PHP_EOL;
            var_dump($conn);
            echo PHP_EOL;
            echo $connStr;
            echo PHP_EOL;
            return null;
        }
        echo "Connection Success" . PHP_EOL;
        oci_set_call_timeout($conn, 3600000);
        return $conn;
    }
    oci_set_call_timeout($conn, 3600000);
    echo "Connection Success" . PHP_EOL;
    return $conn;
}
function queryBuilder($database, $table, $cols, $where = null)
{
    $where = whereHandler($where);
    $cols  = colsHandler($cols);
    $query = "SELECT " . $cols . " FROM " . $database . "." . $table . " " . $where;
    return $query;

}
function colsHandler($cols)
{

    $columns = array();
    foreach ($cols as $colKey => $colValue) {
        $column  = $colKey;
        $convert = $colValue["convert"];
        if ($convert == true) {
            $convertFrom = $colValue["convertFrom"];
            $convertTo   = $colValue["convertTo"];
            $columns[]   = "CONVERT(" . $column . ",'" . $convertTo . "','" . $convertFrom . "') AS " . $column;
        } else {
            $columns[] = $column;
        }
    }
    $resultCols = implode(",", $columns);
    return $resultCols;
}
function whereHandler($where)
{
    $syncConfig = loadConfig()["sync"];
    $rowLimit   = $syncConfig["source_rows_limit"];
    if (!is_null($where) && is_array($where) && count($where) > 0) {
        $whereStr   = "WHERE ";
        $whereCount = 0;
        $whereItems = $where["keys"];
        foreach ($whereItems as $whereItem) {
            $col  = $whereItem["column"];
            $val  = $whereItem["value"];
            $oprs = $whereItem["operator"];
            if ($whereCount == 0) {
                $whereStr = $whereStr . $col . " " . $oprs . " " . $val;
            } elseif ($whereCount > 1) {
                $whereStr = $whereStr . " AND " . $col . " " . $oprs . " " . $val;
            }
            $whereCount++;
        }
        $whereStr .= " AND rownum <= " . $rowLimit;
        if (isset($where["where"]) && !is_null($where["where"])) {
            $whereStr .= " AND " . $where["where"];
        }
    } else {
        // $whereStr = "WHERE rownum <= " . $rowLimit;
        $whereStr = "";
    }
    return $whereStr;
}
function dbEncoding($connectionData)
{
    $conn = oiConnect($connectionData);
    if (is_null($conn)) {
        return null;
    }
    $sql   = "select * from nls_database_parameters where parameter='NLS_CHARACTERSET'";
    $query = oci_parse($conn, $sql);
    oci_execute($query);
    oci_fetch_all($query, $res, null, null, OCI_FETCHSTATEMENT_BY_ROW);
    $result = $res[0]["VALUE"];
    oci_free_statement($query);
    oci_close($conn);
    return $result;
}
function oiSelect($connectionData, $encoding, $database, $table, $columns, $where = "")
{
    $dbEncoding = loadConfig()["oracle"]["db_encoding"];
    if ($dbEncoding == "db") {
        $dbEncodingRes = dbEncoding($connectionData);
        if (!is_null($dbEncodingRes)) {
            $encoding = $dbEncodingRes;
        }
    }
    $conn = oiConnect($connectionData, $encoding);
    if (is_null($conn)) {
        return "rc-error";
    }
    $sql = queryBuilder($database, $table, $columns, $where);
    // var_dump($sql);
    // exit;
    // if (strpos($sql, 'rowid') !== false) {

    //     var_dump(getfirstRowId($conn, $sql));
    //     exit;
    // } else {
    //     echo " no rowid" . PHP_EOL;
    // }

    echo "oci_parse" . PHP_EOL;
    $query = oci_parse($conn, $sql);
    echo "oci_execute" . PHP_EOL;
    oci_execute($query);
    oci_fetch_all($query, $res, null, null, OCI_FETCHSTATEMENT_BY_ROW);
    $result = $res;
    oci_free_statement($query);
    oci_close($conn);
    return $result;

}
// function getfirstRowId($conn, $sql)
// {
//     $sql   = str_replace(" rowid > -1 AND ", " ", $sql);
//     $sql   = str_replace("rownum <= 100", "rownum <= 10", $sql);
//     $query = oci_parse($conn, $sql);
//     $rowid = oci_new_descriptor($conn, OCI_D_ROWID);
//     oci_define_by_name($query, "ROWID", $rowid);

//     oci_execute($query);
//     oci_fetch_all($query, $res, null, null, OCI_FETCHSTATEMENT_BY_ROW);
//     $result = $res[0]["ROWID"];

//     var_dump($rowid->read(2000));
//     var_dump($result);
//     exit;
//     oci_free_statement($query);
//     return $result;

// }
