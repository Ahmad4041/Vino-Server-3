<?php

class ConfigController
{
    private $dbConnection;

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    public function getThemeSetting($key)
    {
        $sql = "SELECT value FROM banksettings WHERE module = 'Theme Setting' AND `key` = ? AND status = '1' LIMIT 1";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute([$key]);
        return $stmt->fetchColumn();
    }

    public function getAppSetting()
    {
        $sql = "SELECT value FROM banksettings WHERE module = 'App Setting' AND `key` = 'appname' AND status = '1' LIMIT 1";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getAppLogo()
    {
        $sql = "SELECT value FROM banksettings WHERE module = 'App Setting' AND `key` = 'applogo' AND status = '1' LIMIT 1";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getCurrency()
    {
        $sql = "SELECT value FROM banksettings WHERE module = 'App Setting' AND `key` = 'Currency' AND status = '1' LIMIT 1";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getlandingpageSetting($key)
    {
        $sql = "SELECT value FROM banksettings WHERE module = 'Landing Page Setting' AND `key` = ? AND status = '1' LIMIT 1";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute([$key]);
        return $stmt->fetchColumn();
    }

    public function getConfigKeyValue($bankid, $key)
    {
        $sql = "SELECT value FROM banksettings WHERE `key` = ? AND bankname = ? LIMIT 1";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute([$key, $bankid]);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : '';
    }

    public function getConfigKeyValueData($bankid, $key)
    {
        $sql = "SELECT `value`, `updated_at` FROM banksettings WHERE `key` = ? AND bankname = ? LIMIT 1";
        $stmt = $this->dbConnection->prepare($sql);
        $stmt->execute([$key, $bankid]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? $result : ['value' => '', 'updated_at' => null];
    }
}
