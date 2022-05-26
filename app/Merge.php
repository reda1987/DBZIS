<?php

function merge()
{
    $sources = getSources();
    foreach ($sources as $source) {
        echo "******** Merge Source: " . $source . " ********" . PHP_EOL;
        $tables = getTables($source);
        foreach ($tables as $table) {
            echo "####### Merge Table: " . $table . " #######" . PHP_EOL;
            mergeSourceTableData($source, $table);
        }
        echo "************************************************" . PHP_EOL;
    }

}

function mergeSourceTableData($source, $table)
{

    $sourceData     = getSourceData($source);
    $sourceDatabase = $sourceData["connection"]["database"];
    $targetDatabase = loadConfig()["merge"]["merge_database"];
    echo " -- ####### Getting Data Count #######" . PHP_EOL;
    $sourceTableData = getSourceTableData($source, $sourceDatabase, $table);
    $sourceDataCount = (!is_null($sourceTableData)) ? count($sourceTableData) : 0;
    echo "####### Data Count: " . $sourceDataCount . " #######" . PHP_EOL;
    echo " -- ####### Start Merge to target #######" . PHP_EOL;
    mergeDataToTarget($sourceTableData, $targetDatabase, $table, $source);
    echo "#####################################" . PHP_EOL;

}
function checkMerge($source, $table)
{
    echo "####### Checking Source -" . $source . "- & Table -" . $table . "- ####### " . PHP_EOL;
    echo "-- ####### Getting Main IDS #######" . PHP_EOL;
    if ($table == "ALL_PATIENTS") {
        $mainIds = checkMergeGetIds_ALL_PATIENTS($source, $table);
    }

    echo "-- #######  Start Validation #######" . PHP_EOL;
    if (!is_null($mainIds)) {
        $notSync = checkMergeSourceValidate($source, $table, $mainIds);
        var_dump($notSync);
    }
    echo "-- #######  End Validation #######" . PHP_EOL;

}

function checkMergeHandleIds($mainIds)
{
    $mainIdsAfter = array();
    foreach ($mainIds as $idsArray) {
        foreach ($idsArray as $key => $value) {
            $mainIdsAfter[$key][] = $value;
        }
    }
    return $mainIdsAfter;
}
function checkMergeGetIds_ALL_PATIENTS($source, $table)
{
    $targetDatabase = loadConfig()["merge"]["merge_database"];
    $connection     = localTarget($targetDatabase);
    $columns        = array("PATIENT_SER");
    $Ids            = checkMergeGetIds($connection, $source, $table, $columns);
    $connection->close();
    $handleIds = null;
    if (!is_null($Ids)) {
        $handleIds = checkMergeHandleIds($Ids);
    }

    return $handleIds;

}
function checkMergeSourceValidate($source, $table, $mainIds)
{
    $sourceData     = getSourceData($source);
    $sourceDatabase = $sourceData["connection"]["database"];
    $connection     = localTarget($sourceDatabase);
    $columns        = checkMergeGetCols($mainIds);
    $columnsSQL     = implode(",", $columns);
    $table          = $source . "_" . $table;
    $where          = checkMergeSourceWhere($mainIds);
    $sql            = "SELECT $columnsSQL FROM $table WHERE $where AND sync_status =\"1\"";
    $result         = $connection->query($sql);
    //var_dump("*** Data SQL *** ", $sql);
    if ($result->num_rows > 0) {
        $resultData = $result->fetch_all(MYSQLI_ASSOC);
        //var_dump("*** Data Count *** ", count($resultData));
        return $resultData;
    }
    return null;
}
function checkMergeGetCols($mainIds)
{
    $cols = array();
    foreach ($mainIds as $column => $values) {
        $cols[] = $column;
    }
    return $cols;
}
function checkMergeSourceWhere($mainIds)
{
    $where     = "";
    $colsCount = 1;
    foreach ($mainIds as $column => $values) {
        if ($colsCount > 1) {
            $where .= " AND ";
        }
        $where .= $column . " NOT IN (";
        $where .= implode(",", $values);
        $where .= ")";
        $colsCount++;
    }
    return $where;
}
function checkMergeGetIds($connection, $source, $table, $columns)
{
    $columnsSQL = implode(",", $columns);
    $sql        = "SELECT $columnsSQL FROM $table WHERE branch=\"$source\"";
    $result     = $connection->query($sql);
    //var_dump("*** Data SQL *** ", $sql);
    if ($result->num_rows > 0) {
        $resultData = $result->fetch_all(MYSQLI_ASSOC);
        //var_dump("*** Data Count *** ", count($resultData));
        return $resultData;
    }
    var_dump($sql);
    return null;
}

function mergeDataToTarget($sourceTableData, $targetDatabase, $table, $source)
{
    $connection  = localTarget($targetDatabase);
    $targetTable = $targetDatabase . "." . $table;
    if (is_null($sourceTableData)) {
        return;
    }
    $cols = mergeColsPrep($sourceTableData);
    $vals = mergeValsPrep($sourceTableData, $source);
    $sql  = "INSERT INTO " . $targetTable . " " . $cols . " VALUES " . $vals;
    if ($connection->query($sql) === true) {
        $connection->close();
        return true;
    } else {
        echo "*******************************************" . PHP_EOL;
        var_dump("***** ERROR BULK *********", $connection->error);
        var_dump($sql);
        echo "*******************************************" . PHP_EOL;
        $valsArray = explode("),(", $vals);
        foreach ($valsArray as $val) {
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
            $sql = "INSERT INTO " . $targetTable . " " . $cols . " VALUES " . $val;
            if ($connection->query($sql) === true) {
                continue;
            } else {
                var_dump($connection->error);
                // var_dump($sql);
                continue;
            }
        }
        $connection->close();
        return true;
    }

}

function getSourceTableData($source, $sourceDatabase, $table)
{
    $limit       = loadConfig()["merge"]["merge_limit"];
    $connection  = localTarget($sourceDatabase);
    $sourceTable = $sourceDatabase . "." . $source . "_" . $table;
    $sql         = "SELECT * FROM $sourceTable WHERE sync_status =\"0\" LIMIT $limit ";
    $result      = $connection->query($sql);
    //var_dump("*** Data SQL *** ", $sql);
    if ($result->num_rows > 0) {
        $resultData = $result->fetch_all(MYSQLI_ASSOC);
        echo " ---  ####### Start Set Sync Status #######" . PHP_EOL;
        setSyncStatus($connection, $sourceTable, $resultData, $syncStatus = 1);
        echo " ---  ####### End Set Sync Status #######" . PHP_EOL;
        $connection->close();
        //var_dump("*** Data Count *** ", count($resultData));
        return $resultData;
    }
    return null;

}
function mergeValsPrep($data, $source)
{
    if (count($data)) {
        $vals = array();
        foreach ($data as $row) {
            unset($row["sync_status"]);
            unset($row["uid"]);
            $row["branch"] = $source;
            $rowVals       = array_values($row);
            $rowValArray   = array();

            foreach ($rowVals as $rowVal) {
                $rowVal        = stripcslashes($rowVal);
                $rowVal        = str_replace("'", "", $rowVal);
                $rowValArray[] = "'" . $rowVal . "'";
            }
            $strVals = implode(",", $rowValArray);
            $valRes  = "(" . $strVals . ")";
            $vals[]  = $valRes;
        }

        $valsStr = implode(",", $vals);
        return $valsStr;
    }

    return null;
}
function mergeColsPrep($data)
{
    if (count($data)) {
        $cols    = array_keys($data[0]);
        $colsRes = "(" . implode(",", $cols) . ")";
        $colsRes = str_replace("sync_status", "branch", $colsRes);
        $colsRes = str_replace("uid", "", $colsRes);
        $colsRes = str_replace("branch,", "branch", $colsRes);
        return $colsRes;
    }
    return null;

}

function setSyncStatus($connection, $sourceTable, $data, $syncStatus = 0)
{
    $connection->autocommit(false);
    $connection->begin_transaction();
    foreach ($data as $item) {
        // $where = array();
        // foreach ($item as $key => $value) {
        //     if (isset($value) && !is_null($value) && !empty($value)) {
        //         $where[] = $key . " = " . "\"$value\"";
        //     }

        // }
        $uid = $item["uid"];
        // $whereStr  = implode(" and ", $where);
        $whereStr  = " uid=" . $uid;
        $updateSql = "UPDATE $sourceTable set sync_status = $syncStatus WHERE $whereStr";
        try {
            $connection->query($updateSql);
        } catch (Exception $e) {

        }
    }

    $connection->commit();
    $connection->autocommit(true);
}
