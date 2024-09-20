<?php

class UtilityDemo
{
    public static $localDb = 'mysql';
    public static $bankDb = '';
    public static $appFeature=[
        //Top Bar
        'add_funds' => true,
        'send_money' => true,
        'peer_pay' => false,
        'scan_pay' => false,
        //Quick Access
        'topup_mobile' => true,
        'bill_payment' => true,
        'quick_loan' => true,
        'others' => false,
        //Botom Bar
        'piggy' => true,
        'mycards' => true,        
    ];
    public static function getDatabaseConnection($bankId, $localConnection = false)
    {
        if (!$localConnection) {
            if ($bankId !== null) {
                $bankDbConnection = $bankId;
                // return $bankDbConnection;
                return Database::getConnection($bankDbConnection);
            }
            return self::$bankDb;
        } else {
            return self::$localDb;
        }
    }
}
