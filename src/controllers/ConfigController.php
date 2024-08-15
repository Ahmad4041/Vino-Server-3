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

    function getTelcoNetworks()
    {
        $live = false;
        if ($live) {
            $charms = new VTPassController();
            $data = $charms->getAirtimeServices('airtime');
        } else {
            $localDb = new LocalDbController(Database::getConnection('mysql'));
            $data = $localDb->getResponse('airtime');
            $data = unserialize($data['response']);
        }
        $response = [
            'message'   => ErrorCodes::$SUCCESS_FETCH[1],
            'timestamp' => date('Y-m-d H:i:s'),
            'status'    => ErrorCodes::$SUCCESS_FETCH[0],
            'body'      => $data,
        ];
        return $response;
    }

    function getUtilities($bankid, $services = null)
    {
        try {
            $utilitydata = array();
            if ($services == null) {
                $utilitydata[] = [
                    "catgeoryName" => "Internet  Data Bundles",
                    "categoryCode" => "Internet",
                ];
                $utilitydata[] = [
                    "catgeoryName" => "Television Cable Subscription",
                    "categoryCode" => "Cable",
                ];
                $utilitydata[] = [
                    "catgeoryName" => "Electricity Bills",
                    "categoryCode" => "Electricity",
                ];
            } else {
                switch ($services) {
                    case 'Internet':
                        $utilitydata[] = [
                            "catgeoryName" => "Internet  Data Bundles",
                            "categoryCode" => "Internet",
                            "providers" => $this->getServiceCategory('data', true)
                        ];
                        break;
                    case 'Cable':
                        $utilitydata[] = [
                            "catgeoryName" => "Television Cable Subscription",
                            "categoryCode" => "Cable",
                            "providers" => $this->getServiceCategory('tv-subscription', true)
                        ];
                        break;
                    case 'Electricity':
                        $utilitydata[] = [
                            "catgeoryName" => "Electricity Bills",
                            "categoryCode" => "Electricity",
                            "providers" => $this->getServiceCategory('electricity-bill', true)
                        ];
                        break;
                    case 'all':
                        $utilitydata[] = [
                            "catgeoryName" => "Internet  Data Bundles",
                            "categoryCode" => "Internet",
                            "providers" => $this->getServiceCategory('data', true)
                        ];
                        $utilitydata[] = [
                            "catgeoryName" => "Television Cable Subscription",
                            "categoryCode" => "Cable",
                            "providers" => $this->getServiceCategory('tv-subscription', true)
                        ];
                        $utilitydata[] = [
                            "catgeoryName" => "Electricity Bills",
                            "categoryCode" => "Electricity",
                            "providers" => $this->getServiceCategory('electricity-bill', true)
                        ];
                        break;
                    default:
                        $utilitydata[] = [
                            "catgeoryName" => "Internet  Data Bundles",
                            "categoryCode" => "Internet",
                        ];
                        $utilitydata[] = [
                            "catgeoryName" => "Television Cable Subscription",
                            "categoryCode" => "Cable",
                        ];
                        $utilitydata[] = [
                            "catgeoryName" => "Electricity Bills",
                            "categoryCode" => "Electricity",
                        ];
                }
            }
            $message = ErrorCodes::$SUCCESS_FETCH_UTILITIES[1];
            $dcode = ErrorCodes::$SUCCESS_FETCH_UTILITIES[0];
            $code = 200;
            return sendCustomResponse($message, $utilitydata, $dcode, $code);
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    function getServiceCategory($service, $subcategory = false)
    {
        $vtpass = new VTPassController();
        $categories = $vtpass->getServiceByCategory($service);
        $count = 0;
        $res = array_values(array_filter(array_map(function ($row) use ($vtpass, &$count, $subcategory) {
            $count++;
            if (!$subcategory) {
                return [
                    "service_type" => $row['serviceID'],
                    "name" => $row['name'],
                    "shortname" => $row['serviceID'],
                    "product_id" => $count,
                ];
            }
            return [
                "packages" =>  $vtpass->getServiceByVariation($row['serviceID']),
                "service_type" => $row['serviceID'],
                "name" => $row['name'],
                "shortname" => $row['serviceID'],
                "product_id" => $count,
            ];
        }, $categories)));
        return $res;
    }

    public function getBankListWithoutAuth($bankid)
    {
        try {
            $localDbConnection = new BankDbController(Database::getConnection($bankid));
            $banks = $localDbConnection->getAllbanks($bankid);

            if ($banks['code'] == 200) {
                $data  = $banks['data'];
                $message = ErrorCodes::$SUCCESS_AVAILABLE_BANK_LIST[1];
                $dcode = ErrorCodes::$SUCCESS_AVAILABLE_BANK_LIST[0];
                $code = 200;
                return sendCustomResponse($message, $data, $dcode, $code);
            } else {
                $message = $banks['message'];
                $dcode = $banks['code'];
                $code = 404;
                return sendCustomResponse($message, [], $dcode, $code);
            }
        } catch (Exception $e) {
            return [
                'dcode' => 500,
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }
}
