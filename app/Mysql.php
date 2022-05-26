<?php
function mysqlConnect($connectionData)
{
    $conn = new mysqli(
        $connectionData['host'],
        $connectionData['user'],
        $connectionData['pass'],
        $connectionData['database'],
        $connectionData['port']
    );
    // echo debug_backtrace()[1]['function'];
    // echo PHP_EOL;
    if ($conn->connect_error) {

        echo "Connection failed: " . $conn->connect_error . PHP_EOL;
        var_dump($connectionData);
        echo PHP_EOL;
        exit;
    }
    $conn->set_charset('utf8');
    return $conn;
}

function localTarget($sourceDatabase)
{
    $connectionData = databaseConfig();
    unset($connectionData["database"]);
    $connectionData["database"] = $sourceDatabase;
    $conn                       = mysqlConnect($connectionData);
    return $conn;
}
function remoteTarget($target, $sourceDatabase)
{
    //code
}

function mysqlBulkInsert($connection, $data, $sourceDatabase, $table)
{
    $cols = colsPrep($data);
    $vals = valsPrep($data);
    $sql  = "INSERT INTO " . $sourceDatabase . "." . $table . " " . $cols . " VALUES " . $vals;
    if ($connection->query($sql) === true) {
        echo "MySQL Bulk Insert Success" . PHP_EOL;
        return true;
    } else {
        echo "MySQL Bulk Insert Failed" . PHP_EOL;
        $connection  = localTarget($sourceDatabase);
        $valsArray   = explode("),(", $vals);
        $countIsert  = count($valsArray);
        $insertCount = 0;
        foreach ($valsArray as $val) {
            $insertCount++;
            $val        = str_replace(")", "", $val);
            $val        = str_replace("(", "", $val);
            $valArray   = explode(",", $val);
            $valToArray = array();
            foreach ($valArray as $valItem) {
                $vVal         = stripcslashes($valItem);
                $valToArray[] = $vVal;
            }
            $val = implode(",", $valToArray);
            $val = "(" . $val . ")";
            $sql = "INSERT INTO " . $sourceDatabase . "." . $table . " " . $cols . " VALUES " . $val;
            echo $insertCount . "/" . $countIsert . PHP_EOL;
            if ($connection->query($sql) === true) {
                echo "Insert Success" . PHP_EOL;
                continue;
            } else {
                var_dump($connection->error);
                var_dump($sql);
                continue;
            }
        }
        return true;
    }
}

function valsPrep($data)
{
    $vals = array();
    foreach ($data as $row) {
        $rowVals     = array_values($row);
        $rowValArray = array();
        foreach ($rowVals as $rowVal) {

            $charset       = getCharset($rowVal);
            $newStr        = convertToUTF($rowVal, $charset);
            $newStr        = stripcslashes($newStr);
            $newStr        = str_replace("'", "", $newStr);
            $rowValArray[] = "'" . $newStr . "'";
        }
        $strVals = implode(",", $rowValArray);
        $valRes  = "(" . $strVals . ")";
        $vals[]  = $valRes;
    }
    $valsStr = implode(",", $vals);
    return $valsStr;
}

function colsPrep($data)
{
    $cols = array();
    foreach ($data as $row) {
        $cols = colsFetch($cols, array_keys($row));
    }
    $colsRes = "(" . implode(",", $cols) . ")";
    return $colsRes;
}

function colsFetch($cols, $keys)
{
    $cols = $cols;
    foreach ($keys as $key) {
        if (!in_array($key, $cols)) {
            $cols[] = $key;
        }
    }
    return $cols;
}
