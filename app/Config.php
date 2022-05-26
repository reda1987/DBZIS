<?php

function loadConfig()
{
    global $pwd;
    $configFile = $pwd . "/config.ini";
    $config     = parse_ini_file($configFile, true);
    return $config;
}

function databaseConfig()
{
    $config         = loadConfig();
    $databaseConfig = $config["database"];
    return $databaseConfig;
}

function dbzisDatabaseConnect()
{
    $connectionData = databaseConfig();
    $conn           = mysqlConnect($connectionData);
    return $conn;

}
