<?php

trait DBConnection 
{
    protected function getExtDb() 
    {  
        $dbHost = getenv( 'EXTERNAL_DB_HOST' );
        $dbUser = getenv( 'EXTERNAL_DB_USER' );
        $dbPassword = getenv( 'EXTERNAL_DB_PASSWORD' );
        $dbName = getenv( 'EXTERNAL_DB_NAME' );

        try {
            $dsn = "mysql:host=giantbomb-mysql;dbname={$dbName};charset=utf8mb4";
            $db = new PDO($dsn, $dbUser, $dbPassword);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // increase group_concat length for relationships exceeding 1k
            $db->exec("SET SESSION group_concat_max_len = 1000000;");

            $dbWrapper = new PdoDbWrapper($db);
        } catch ( PDOException $e ) {
            $this->output("Database connection or query failed: " . $e->getMessage(), true);
        }

        echo "Using external db.\n";

        return $dbWrapper;
    }

    protected function getApiDb() 
    { 
        $db = $this->getDB(DB_PRIMARY, [], getenv('MARIADB_API_DUMP_DATABASE')); 
        $dbWrapper = new MWDbWrapper($db);

        echo "Using api db.\n";

        return $dbWrapper;
    }

}