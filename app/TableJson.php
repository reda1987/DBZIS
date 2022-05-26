<?php

function createTableJson($table)
{
    global $pwd;
    $tableFile = $pwd . "/tmp/" . $table . ".json";
    $tableJson = readTableFile($table);
    createJsonFile($tableJson, $table);
    if (is_file($tableFile)) {
        echo "Table json file (" . $tableFile . ") has been created successfuly" . PHP_EOL;
    } else {
        echo "Unable to create Table json file (" . $tableFile . ") " . PHP_EOL;
    }

}
function readTableFile($table)
{
    global $pwd;
    $tableFile = $pwd . "/tmp/" . $table . ".txt";
    if (is_file($tableFile)) {
        $tableFileContent = file_get_contents($tableFile);
        $columns          = explode("\n", $tableFileContent);
        $tableData        = array();
        foreach ($columns as $column) {
            $tableData[$table]["columns"][$column]["required"] = true;
            $tableData[$table]["columns"][$column]["convert"]  = false;
        }
        $tableData[$table]["settings"]["syncEvery"] = 1;
        $tableData[$table]["settings"]["timeUnit"]  = "minute";
        $tableData[$table]["settings"]["syncKeys"]  = [];
        $tableData[$table]["settings"]["target"]    = ["local"];
        $tableJson                                  = json_encode($tableData);
        return $tableJson;
    } else {
        echo "Table .txt file is not exist in tmp directory" . PHP_EOL;
        return;
    }

}

function createJsonFile($tableJson, $table)
{
    global $pwd;
    $tableFile     = $pwd . "/tmp/" . $table . ".json";
    $openTableFile = fopen($tableFile, "w") or die("Unable to open file!");
    fwrite($openTableFile, $tableJson);
    fclose($openTableFile);
}
