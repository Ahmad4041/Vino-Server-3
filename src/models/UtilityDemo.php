<?php

class UtilityDemo
{
    public static $localDb = 'mysql';
    public static $bankDb = '';

    public static function getDatabaseConnection($bankId, $localConnection = false)
    {
        if (!$localConnection) {
            if ($bankId !== null) {
                $bankDbConnection = $bankId;
                return $bankDbConnection;
            }
            return self::$bankDb;
        } else {
            return self::$localDb;
        }
    }
}
