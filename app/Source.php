<?php
$sourcesDir = $pwd . "/source/";
function loadSources()
{
    global $sourcesDir;
    $sourcesDirFiles = scandir($sourcesDir);
    foreach ($sourcesDirFiles as $key => $file) {
        $pathParts = pathinfo($file);
        if ($pathParts['extension'] != "json") {
            unset($sourcesDirFiles[$key]);
        }
    }
    return $sourcesDirFiles;
}

function getSources()
{
    global $sourcesDir;
    $sourcesFiles = loadSources();
    $sources      = array();
    foreach ($sourcesFiles as $key => $sourceFile) {
        $sourceName = pathinfo($sourceFile)['filename'];
        $sources[]  = $sourceName;
    }
    return $sources;
}
function getSourceData($sourceName)
{
    global $sourcesDir;
    $sourceFile = $sourceName . ".json";
    $sourceData = json_decode(file_get_contents($sourcesDir . $sourceFile), true);
    return $sourceData;
}

function sourceParser(array $source)
{
    // code...
}
function getConnectionData($sourceName)
{
    $source         = getSourceData($sourceName);
    $connectionData = array(
        'host'         => $source["connection"]["host"],
        'port'         => $source["connection"]["port"],
        'sid'          => $source["connection"]["sid"],
        'SERVICE_NAME' => $source["connection"]["SERVICE_NAME"],
        'username'     => $source["connection"]["username"],
        'password'     => $source["connection"]["password"],
    );
    return $connectionData;
}
function getTables($sourceName)
{
    $source       = getSourceData($sourceName);
    $sourceTables = $source["tables"];
    $tables       = array();
    foreach ($sourceTables as $tableName => $tableData) {
        $tables[] = $tableName;
    }
    return $tables;
}

function getTableSettings(array $sourceName, string $tableName)
{
    $source        = getSourceData($sourceName);
    $tableSettings = $source["tables"][$tableName]["settings"];
    return $tableSettings;
}

function getTableCols($sourceName, $tableName)
{
    $source          = getSourceData($sourceName);
    $sourceTableCols = $source["tables"][$tableName]["columns"];
    return $sourceTableCols;
}
