<?php

function generateSyncTasks()
{
    cleanSyncTask();
    $generatedTasks = 0;
    $syncConfig     = loadConfig()['sync'];
    $scheduleAhead  = $syncConfig["schedule_ahead"];
    echo "Getting Sources" . PHP_EOL;
    $sources = getSources();
    foreach ($sources as $source) {
        if (isset($source)) {
            $sourceData = getSourceData($source);
            if (!isset($sourceData["tables"])) {
                continue;
            }
            $sourceTables = $sourceData["tables"];
            echo "- Getting Source " . $source . PHP_EOL;
            $targetSyncTables = targetSyncTables($source, $sourceTables);
            foreach ($sourceTables as $sourceTableName => $sourceTableData) {
                echo "-- Getting Source Tables " . $sourceTableName . PHP_EOL;
                $sourceSettings = $sourceTableData["settings"];
                if (isSyncDue($source, $sourceSettings, $sourceTableName)) {
                    echo "-- Sync Is Due " . $sourceTableName . PHP_EOL;
                    $targets = $sourceSettings["target"];
                    foreach ($targets as $target) {
                        $pendingTasks = pendingTasksCount($source, $sourceTableName, $target);
                        if ($pendingTasks < $scheduleAhead) {
                            echo "-- Add Sync Task " . PHP_EOL;
                            addSyncTask($source, $target, $sourceTableName);
                            $generatedTasks++;
                        }
                    }
                }
                echo "-- Sync Is NOT Due " . $sourceTableName . PHP_EOL;
            }
        }
    }
}

function syncExe($source = null, $synctable = null)
{
    $task = getPendingTask($source, $synctable);
    if (!is_null($task)) {
        $source          = $task["source"];
        $target          = $task["target"];
        $sourceTableName = $task["synctable"];

        $startTask = setTaskStatus($task, "running");
        echo "Start Sync Task Id " . $task["id"] . " Source:" . $source . " target " . $target . " Table " . $sourceTableName . PHP_EOL;
        $runCount = loadConfig()['sync']["run_count"];
        for ($i = 0; $i < $runCount; $i++) {
            if ($startTask) {
                $sourceData       = getSourceData($task["source"]);
                $sourceEncoding   = $sourceData["connection"]["encoding"];
                $sourceDatabase   = $sourceData["connection"]["database"];
                $sourceConnection = getConnectionData($source);
                $sourceColumns    = getTableCols($source, $sourceTableName);
                $where            = whereBuilder($source, $sourceData, $sourceTableName);
                $sourceSyncData   = oiSelect($sourceConnection, $sourceEncoding, $sourceDatabase, $sourceTableName, $sourceColumns, $where);
                if ($sourceSyncData == "rc-error") {
                    setTaskStatus($task, "rc-error");
                    break;
                }
                if (count($sourceSyncData)) {
                    if ($target == "local") {
                        $targetConnection = localTarget($sourceDatabase);
                    } else {
                        $targetConnection = remoteTarget($target, $sourceDatabase);
                    }
                    $targetTable    = $source . "_" . $sourceTableName;
                    $lastItem       = end($sourceSyncData);
                    $currentLastKey = currentLastKey($sourceData, $sourceTableName, $lastItem);
                    if (is_null($currentLastKey)) {
                        truncateTable($targetConnection, $sourceDatabase, $targetTable);
                    }
                    echo "Start MySQL Insert" . PHP_EOL;
                    mysqlBulkInsert($targetConnection, $sourceSyncData, $sourceDatabase, $targetTable);
                    setLastSync($source, $sourceTableName, $currentLastKey);
                }
                setTaskStatus($task, "complete");
                echo "-- Sync Completed - Number of new records " . count($sourceSyncData) . PHP_EOL;
                if ($targetConnection) {
                    $targetConnection->close();
                }

            }
            if (is_null($currentLastKey)) {
                break;
            }
            if (!isset($sourceData["tables"][$sourceTableName]["settings"]["syncKeys"])) {
                break;
            }
        }

        return;
    }
    return 0;

}
function targetSyncTables($source, $sourceTables)
{
    foreach ($sourceTables as $sourceTableName => $sourceTableData) {
        $targetTable    = $source . "_" . $sourceTableName;
        $targets        = $sourceTableData["settings"]["target"];
        $sourceData     = getSourceData($source);
        $sourceDatabase = $sourceData["connection"]["database"];
        foreach ($targets as $target) {
            if ($target == "local") {
                $targetConnection = localTarget($sourceDatabase);
                $checkTableResult = checkTableResult($targetConnection, $targetTable);
                if ($checkTableResult === true) {
                    $checkTableCols = checkTableCols($targetConnection, $targetTable, $sourceTableData, $sourceDatabase);
                    if ($checkTableCols === false) {
                        $createTable = createTargetTable($targetConnection, $targetTable, $sourceTableData);
                        echo "--- Reset Table " . $targetTable . PHP_EOL;
                        restSyncSource($source, $targetTable);
                    }
                } elseif ($checkTableResult === false) {
                    $createTable = createTargetTable($targetConnection, $targetTable, $sourceTableData);
                }
                $targetConnection->close();
            } else {
                $targetConnection = remoteTarget($target, $sourceDatabase);
            }
        }

    }

}
function restSyncSource($source, $targetTable)
{
    echo " -- Rest Sync --Source-- " . $source . " --Table-- " . $targetTable . PHP_EOL;
    $dbzisConnect        = dbzisDatabaseConnect();
    $targetTable         = str_replace($source . "_", "", $targetTable);
    $database            = loadConfig()["database"]["database"];
    $resetLastSyncSQL    = "DELETE FROM " . $database . ".lastsync WHERE source = '" . $source . "' AND synctable = '" . $targetTable . "'";
    $resetLastSyncResult = $dbzisConnect->query($resetLastSyncSQL);
    $dbzisConnect->close();

}
function truncateTable($targetConnection, $sourceDatabase, $targetTable)
{
    echo " -- TRUNCATE TABLE " . $targetTable . PHP_EOL;
    $dropSQL = "TRUNCATE TABLE " . $sourceDatabase . "." . $targetTable;
    $targetConnection->query($dropSQL);
}
function checkTableCols($targetConnection, $targetTable, $sourceTableData, $sourceDatabase)
{
    $columnsCheck = false;
    $cols         = $sourceTableData["columns"];
    $columns      = array();
    foreach ($cols as $col => $colData) {
        $columns[] = $col;
    }
    $checkTableColsSQL = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . $sourceDatabase . "' AND TABLE_NAME = '" . $targetTable . "'";

    $checkTableColsResult = $targetConnection->query($checkTableColsSQL);

    if ($checkTableColsResult->num_rows > 0) {
        $tableCols = array();
        while ($row = mysqli_fetch_assoc($checkTableColsResult)) {
            $tableCols[] = $row["COLUMN_NAME"];
        }
        if (count(array_diff($columns, $tableCols)) || count(array_diff($tableCols, $columns))) {
            $diffColTable = array_diff($tableCols, $columns);
            $columnsCheck = false;
            if (count($diffColTable) == 2 && in_array("sync_status", $diffColTable) && in_array("uid", $diffColTable)) {
                $columnsCheck = true;
            }

        } else {
            $columnsCheck = true;
        }
        if ($columnsCheck === false) {
            echo "Drop Table " . $targetTable . PHP_EOL;
            $dropSQL = "DROP TABLE " . $targetTable;
            $targetConnection->query($dropSQL);
        }

    }

    return $columnsCheck;

}
function createTargetTable($targetConnection, $targetTable, $sourceTableData)
{
    echo "Creating Target Table " . $targetTable . PHP_EOL;
    $cols    = $sourceTableData["columns"];
    $columns = array();
    foreach ($cols as $col => $colData) {
        $columns[] = $col . " VARCHAR(100) NOT NULL";
    }
    $columnsStr = implode(",", $columns);
    $columnsStr .= ", sync_status INT DEFAULT 0 NOT NULL";
    $columnsStr .= ", uid int NOT NULL AUTO_INCREMENT";
    $columnsStr .= ", PRIMARY KEY (uid)";
    $createSQL = "CREATE TABLE $targetTable ( ";
    $createSQL .= $columnsStr . " )";
    $checkTableResult = $targetConnection->query($createSQL);
    if ($targetConnection->query($createSQL) === true) {
        return true;
    } else {
        return false;
    }

}
function checkTableResult($targetConnection, $targetTable)
{
    $tableExist       = false;
    $checkTableSQL    = "show tables like \"$targetTable\"";
    $checkTableResult = $targetConnection->query($checkTableSQL);
    if ($checkTableResult->num_rows > 0) {
        $tables = mysqli_fetch_assoc($checkTableResult);
        // $targetConnection->close();
        foreach ($tables as $table) {
            if ($table === $targetTable) {
                $tableExist = true;
            }
        }
    }
    return $tableExist;
}
function currentLastKey($sourceData, $sourceTableName, $lastItem)
{
    $syncKeys = (isset($sourceData["tables"][$sourceTableName]["settings"]["syncKeys"])) ? $sourceData["tables"][$sourceTableName]["settings"]["syncKeys"] : null;
    $keys     = array();
    if (!empty($syncKeys) && count($syncKeys)) {
        foreach ($syncKeys as $syncKey => $value) {
            $keys[] = $lastItem[$syncKey];
        }
        $currentLastKey = implode(",", $keys);

        return $currentLastKey;
    }
    return $syncKeys;

}
function whereBuilder($source, $sourceData, $sourceTableName)
{

    $customWhere = (isset($sourceData["tables"][$sourceTableName]["settings"]["WHERE"])) ? $sourceData["tables"][$sourceTableName]["settings"]["WHERE"] : null;

    $syncKeys = (isset($sourceData["tables"][$sourceTableName]["settings"]["syncKeys"])) ? $sourceData["tables"][$sourceTableName]["settings"]["syncKeys"] : null;
    if (!is_null($syncKeys)) {
        $lastKey = getLastKey($source, $sourceTableName);
        if (!is_null($lastKey)) {
            $where      = array();
            $lastKeySeq = 0;
            foreach ($syncKeys as $column => $operator) {
                $where["keys"][] = array("column" => $column, "operator" => $operator, "value" => $lastKey[$lastKeySeq]);
                $lastKeySeq++;
            }
            $where["where"] = $customWhere;
            return $where;
        }
    }
    return "";
}
function getLastKey($source, $sourceTableName)
{
    $lastCol = getLastCol($source, $sourceTableName, "lastkey");
    if ($lastCol == "" || empty($lastCol) || is_null($lastCol)) {
        $result = ["-1"];
    } else {
        if (strpos($lastCol, ",")) {
            $result = explode(",", $lastCol);
        } else {
            $result = [$lastCol];
        }

    }
    return $result;
}

function addSyncTask($source, $target, $sourceTableName)
{
    $created      = date('Y-m-d H:i:s', strtotime("now"));
    $dbzisConnect = dbzisDatabaseConnect();
    $sql          = "INSERT INTO sync_tasks (source,target,synctable,created,status) ";
    $sql .= "VALUES (\"$source\", \"$target\", \"$sourceTableName\",\"$created\",\"pending\")";
    if ($dbzisConnect->query($sql) === true) {
        $dbzisConnect->close();
        return true;
    } else {
        $dbzisConnect->close();
        return false;
    }
}
function isSyncDue($source, $sourceSettings, $sourceTableName)
{
    $lastSync   = getLastCol($source, $sourceTableName, "lastsync");
    $syncEvery  = $sourceSettings["syncEvery"];
    $timeUnit   = $sourceSettings["timeUnit"];
    $syncEveryM = ($timeUnit == "hour") ? $syncEvery * 60 : $syncEvery * 1;
    $nowTime    = date('Y-m-d H:i:s', strtotime("now"));
    $fromTime   = strtotime($lastSync);
    $toTime     = strtotime($nowTime);
    $timeDiff   = round(abs($toTime - $fromTime) / 60, 2);
    if ($timeDiff > $syncEveryM) {
        return true;
    }

    return false;

}

function setTaskStatus($task, $status)
{
    $nowTime      = date('Y-m-d H:i:s', strtotime("now"));
    $taskId       = intval($task['id']);
    $dbzisConnect = dbzisDatabaseConnect();
    if ($status == "running") {
        $sql = "UPDATE sync_tasks SET startsync=\"$nowTime\" , status=\"$status\" WHERE id = $taskId";
    } elseif ($status == "complete") {
        $sql = "UPDATE sync_tasks SET endsync=\"$nowTime\" , status=\"$status\" WHERE id = $taskId";
    } elseif ($status == "rc-error") {
        $sql = "UPDATE sync_tasks SET endsync=\"$nowTime\" , status=\"$status\" WHERE id = $taskId";
    }
    if ($dbzisConnect->query($sql) === true) {
        $dbzisConnect->close();
        return true;
    } else {
        $dbzisConnect->close();
        return false;
    }
}
function getPendingTask($source = null, $synctable = null)
{
    deleteHangedRunningTasks();
    $dbzisConnect = dbzisDatabaseConnect();
    $sql          = "SELECT * FROM sync_tasks WHERE status =\"pending\"";
    if ($source) {
        $sql .= " AND source = \"$source\"";
    }
    if ($synctable) {
        $sql .= " AND synctable = \"$synctable\"";
    }
    $sql .= " LIMIT 1";

    $result = $dbzisConnect->query($sql);
    if ($result->num_rows > 0) {
        $task              = mysqli_fetch_assoc($result);
        $synctable         = $task["synctable"];
        $runningTasksCount = 0;
        $runningTasks      = getRunningTaskBySynctable($task["source"], $synctable);
        if (!is_null($runningTasks)) {
            $runningTasksCount = count($runningTasks);
        }
        if ($runningTasksCount > 0) {
            $tasksRes = implode(",", $runningTasks);
            $tasksRes = "(" . $tasksRes . ")";
            if ($runningTasksCount > 1) {
                $sql = "SELECT * FROM sync_tasks WHERE status =\"pending\" AND synctable NOT IN \"$tasksRes\" ";
            } else {
                $sql = "SELECT * FROM sync_tasks WHERE status =\"pending\" AND synctable != \"$synctable\" ";
            }

            if ($source) {
                $sql .= " AND source = \"$source\"";
            }
            $sql .= " LIMIT 1";
            $result = $dbzisConnect->query($sql);
            if ($result->num_rows > 0) {
                $task = mysqli_fetch_assoc($result);
                $dbzisConnect->close();
                return $task;
            }

            return null;
        }
        return $task;
    }
    return null;
}
function getRunningTaskBySynctable($source, $synctable)
{
    $dbzisConnect = dbzisDatabaseConnect();
    $sql          = "SELECT synctable FROM sync_tasks WHERE status =\"running\" AND source =\"$source\" AND synctable = \"$synctable\"";
    $result       = $dbzisConnect->query($sql);
    if ($result->num_rows > 0) {
        $synctables     = $result->fetch_all(MYSQLI_ASSOC);
        $synctablesTask = array();
        foreach ($synctables as $synctableName) {
            $synctablesTask[] = $synctableName["synctable"];
        }
        $tasks = array_unique($synctablesTask);

        // $tasks = mysqli_fetch_assoc($result);
        $dbzisConnect->close();

        return $tasks;
    }
    return null;
}
function pendingTasksCount($source, $sourceTableName, $target)
{
    $dbzisConnect = dbzisDatabaseConnect();
    $sql          = "SELECT * FROM sync_tasks ";
    $sql .= "WHERE source = \"$source\" AND synctable = \"$sourceTableName\" AND target = \"$target\"";
    $sql .= " AND status =\"pending\"";
    $result       = $dbzisConnect->query($sql);
    $pendingTasks = $result->num_rows;
    $dbzisConnect->close();
    return $pendingTasks;

}
function deleteHangedRunningTasks()
{
    $dbzisConnect = dbzisDatabaseConnect();
    $sql          = "DELETE FROM sync_tasks WHERE status = 'running' AND startsync >   (NOW() - INTERVAL 60 MINUTE)";
    $result       = $dbzisConnect->query($sql);
    //$pendingTasks = $result->num_rows;
    $dbzisConnect->close();

}
function setLastSync($source, $sourceTableName, $currentLastKey)
{
    $nowTime      = date('Y-m-d H:i:s', strtotime("now"));
    $lastSync     = getLastCol($source, $sourceTableName, "lastsync");
    $dbzisConnect = dbzisDatabaseConnect();
    if (!is_null($lastSync)) {
        $sql = "UPDATE lastsync SET lastsync=\"$nowTime\" ,  lastkey =\"$currentLastKey\" WHERE source = \"$source\" AND synctable = \"$sourceTableName\"";
    } else {
        $sql = "INSERT INTO lastsync (source,synctable,lastsync,lastkey) ";
        $sql .= "VALUES (\"$source\", \"$sourceTableName\",\"$nowTime\",\"$currentLastKey\")";
    }
    if ($dbzisConnect->query($sql) === true) {
        $dbzisConnect->close();
        return true;
    } else {
        $dbzisConnect->close();
        return false;
    }
}

function getLastCol($source, $sourceTableName, $col)
{
    $dbzisConnect = dbzisDatabaseConnect();
    $sql          = "SELECT * FROM lastsync ";
    $sql .= "WHERE source = \"$source\" AND synctable = \"$sourceTableName\"";
    $result = $dbzisConnect->query($sql);

    if ($result->num_rows > 0) {
        $row      = mysqli_fetch_assoc($result);
        $lastSync = $row[$col];
        $dbzisConnect->close();
        return $lastSync;
    } else {
        return null;
    }

}
// this patch function to SET CUST_ACC Column in main.ALL_PATIENTS
function setCustAcc()
{
    $connectionData             = array();
    $connectionData['host']     = "127.0.0.1";
    $connectionData['user']     = "root";
    $connectionData['pass']     = "yALBir13sKWj";
    $connectionData['database'] = "";
    $connectionData['port']     = "3306";
    echo "Connecting ..." . PHP_EOL;
    $connection = mysqlConnect($connectionData);
    $connection->autocommit(false);
    $connection->begin_transaction();
    echo "Getting Data ..." . PHP_EOL;
    $ALL_PATIENTS_sql    = "SELECT PATIENT_SER,branch,CRM_ID FROM main.ALL_PATIENTS WHERE CUST_ACC IS NULL";
    $ALL_PATIENTS_result = $connection->query($ALL_PATIENTS_sql);
    if ($ALL_PATIENTS_result->num_rows > 0) {
        $ALL_PATIENTS_resultData = $ALL_PATIENTS_result->fetch_all(MYSQLI_ASSOC);
        echo "Before Each" . PHP_EOL;
        $dataCount   = count($ALL_PATIENTS_resultData);
        $updateCount = 0;
        foreach ($ALL_PATIENTS_resultData as $PATIENTS_resultData) {
            $PATIENT_SER             = $PATIENTS_resultData['PATIENT_SER'];
            $branch                  = $PATIENTS_resultData['branch'];
            $CRM_ID                  = $PATIENTS_resultData['CRM_ID'];
            $source_ALL_PATIENTS_sql = "SELECT CUST_ACC FROM MANTEN.";
            $source_ALL_PATIENTS_sql .= $branch . "_ALL_PATIENTS";
            $source_ALL_PATIENTS_sql .= " WHERE PATIENT_SER = " . $PATIENT_SER . " LIMIT 1";
            $source_ALL_PATIENTS_result     = $connection->query($source_ALL_PATIENTS_sql);
            $source_ALL_PATIENTS_resultData = $source_ALL_PATIENTS_result->fetch_row();
            $CUST_ACC                       = null;
            if (isset($source_ALL_PATIENTS_resultData[0])) {
                $CUST_ACC = $source_ALL_PATIENTS_resultData[0];
            }
            if (strlen($CUST_ACC) > 0) {
                $CUST_ACC              = "\"$CUST_ACC\"";
                $branch                = "\"$branch\"";
                $main_ALL_PATIENTS_sql = "UPDATE main.ALL_PATIENTS SET CUST_ACC = " . $CUST_ACC;
                $main_ALL_PATIENTS_sql .= " WHERE branch = " . $branch . " AND";
                $main_ALL_PATIENTS_sql .= "  PATIENT_SER = " . $PATIENT_SER . " AND";
                $main_ALL_PATIENTS_sql .= "  CRM_ID = " . $CRM_ID;
                $connection->query($main_ALL_PATIENTS_sql);

            }
            $updateCount++;
            echo $updateCount . "/" . $dataCount . PHP_EOL;

        }
        echo "After Each" . PHP_EOL;
        $connection->commit();
        $connection->autocommit(true);
        echo "Commit Done" . PHP_EOL;
    }

}

function cleanSyncTask()
{
    echo "Cleaning Tasks...." . PHP_EOL;
    $dbzisConnect = dbzisDatabaseConnect();
    $sql          = "DELETE FROM sync_tasks WHERE endsync  < DATE_SUB(NOW() , INTERVAL 12 HOUR) AND status =\"complete\"";

    if ($dbzisConnect->query($sql) === true) {
        $dbzisConnect->close();
        return true;
    } else {
        $dbzisConnect->close();
        return false;
    }
}
