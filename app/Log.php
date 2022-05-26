<?php
$logDir = $pwd . "/log/";
function generateSyncTasksLogs()
{
    global $logDir;
    $generateSyncTasksLogs = $logDir . "generateSyncTasks/";
    $sourcesDirFiles       = scandir($generateSyncTasksLogs);
    foreach ($sourcesDirFiles as $key => $file) {
        $pathParts = pathinfo($file);
        if ($pathParts['extension'] != "log") {
            unset($sourcesDirFiles[$key]);
        }
    }
    return $sourcesDirFiles;
}

function cleanGenerateSyncTasks($logFiles)
{
    global $logDir;
    $generateSyncTasksLogs = $logDir . "generateSyncTasks/";
    $now                   = time();
    $keepLog               = loadConfig()["log"]["keep_log"];
    $logsToDelete          = 60 * 60 * $keepLog;
    foreach ($logFiles as $logFile) {
        $logFile = $generateSyncTasksLogs . $logFile;
        if (is_file($logFile)) {
            $logDuration = $now - filemtime($logFile);
            if ($now - filemtime($logFile) >= $logsToDelete) {
                unlink($logFile);
            }
        }
    }
}
function syncExeLogs()
{
    global $logDir;
    $syncExeLogs     = $logDir . "syncExe/";
    $sourcesDirFiles = scandir($syncExeLogs);
    foreach ($sourcesDirFiles as $key => $file) {
        $pathParts = pathinfo($file);
        if ($pathParts['extension'] != "log") {
            unset($sourcesDirFiles[$key]);
        }
    }
    return $sourcesDirFiles;
}

function cleanSyncExe($logFiles)
{
    global $logDir;
    $syncExeLogs  = $logDir . "syncExe/";
    $now          = time();
    $keepLog      = loadConfig()["log"]["keep_log"];
    $logsToDelete = 60 * 60 * $keepLog;
    foreach ($logFiles as $logFile) {
        $logFile = $syncExeLogs . $logFile;
        if (is_file($logFile)) {
            $logDuration = $now - filemtime($logFile);
            if ($now - filemtime($logFile) >= $logsToDelete) {
                unlink($logFile);
            }
        }
    }
}
function cleanLogs()
{
    $generateSyncTasksLogs = generateSyncTasksLogs();
    cleanGenerateSyncTasks($generateSyncTasksLogs);
    $syncExeLogs = syncExeLogs();
    cleanSyncExe($syncExeLogs);

}
