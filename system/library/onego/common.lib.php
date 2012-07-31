<?php
require_once 'php-api/src/OneGoAPI/init.php';
OneGoUtils::initAPILogging();

/**
 * OneGo Opencart extension configuration class - combines settings in config file
 * and settings changed on extension admin page
 */
class OneGoConfig
{
    private static $instance;
    private $onegoConfig;
    private $config;
    
    private function __construct(Config $config)
    {
        require_once DIR_ONEGO.'config.inc.php';
        $this->onegoConfig = $oneGoConfig;
        $this->config = $config;
    }
    
    public static function getInstance()
    {
        $registry = OneGoUtils::getRegistry();
        if (empty(self::$instance)) {
            $config = $registry->get('config');
            self::$instance = new self($config);
        }
        return self::$instance;
    }
    
    public static function get($key)
    {
        $cfg = self::getInstance();
        $val = $cfg->config->get('onego_'.$key);
        if (!is_null($val)) {
            return $val;
        }
        if (isset($cfg->onegoConfig[$key])) {
            return $cfg->onegoConfig[$key];
        }
        return null;
    }
    
    public static function getArray($key)
    {
        $cfg = self::getInstance();
        $val = $cfg->get($key);
        if (!is_null($val) && !is_array($val)) {
            $val = explode('|', $val);
        }
        return $val;
    }
}

/**
 * Container for persisting data
 */
abstract class OneGoPersistentState
{
    abstract protected function initialize();
    abstract protected function getStorageKey();

    public function get($key)
    {
        return isset($this->$key) ? $this->$key : null;
    }
    
    public function reset()
    {
        OneGoUtils::log('reset '.get_class($this));
        $this->initialize();
        $this->save();
    }
    
    public function set($key, $value)
    {
        $this->$key = $value;
        $this->save();
    }
    
    public function __construct() {
        $this->initialize();
    }

    /**
     * Convert state object to array
     *
     * @return array
     */
    public function toArray()
    {
        $arr = get_object_vars($this);
        foreach ($arr as $key => $val){
            if ($val instanceof OneGoPersistentState) {
                $arr[$key] = $val->toArray();
            }
        }
        return $arr;
    }

    /**
     * Save state
     *
     * @return void
     */
    protected function save()
    {
        OneGoUtils::saveToSession($this->getStorageKey(), $this);
    }
    
    /**
     * Singleton loader, with default option
     *
     * @return OneGoPersistentState 
     */
    public static function loadCurrent(OneGoPersistentState $newDefault)
    {
        return OneGoUtils::getFromSession($newDefault->getStorageKey(), $newDefault);
    }
}

class OneGoTransactionState extends OneGoPersistentState
{
    const PREPAID_SPENT = 'spentPrepaid';
    const VGC_REDEEMED = 'redeemedVGC';
    const AGREED_DISCLOSE_EMAIL = 'agreedToDiscloseEmail';
    const BUYER_ANONYMOUS = 'buyerAnonymous';
    
    protected $transaction;
    protected $cartHash;
    protected $spentPrepaid;
    protected $redeemedVGC;
    protected $agreedToDiscloseEmail;
    
    protected function getStorageKey()
    {
        return 'TransactionState';
    }
    
    protected function initialize()
    {
        $this->transaction = null;
        $this->cartHash = null;
        $this->spentPrepaid = false;
        $this->redeemedVGC = false;
        $this->agreedToDiscloseEmail = false;
    }
    
    public function hasAgreedToDiscloseEmail()
    {
        return $this->agreedToDiscloseEmail;
    }
    
    /**
     *
     * @return OneGoTransactionState 
     */
    public static function getCurrent()
    {
        return parent::loadCurrent(new self());
    }
}

class OneGoOAuthTokenState extends OneGoPersistentState
{
    protected $token;
    protected $buyerAnonymous;
    
    protected function getStorageKey()
    {
        return 'OAuthTokenState';
    }
    
    protected function initialize()
    {
        $this->token = null;
        $this->buyerAnonymous = true;
    }
    
    public function isBuyerAnonymous()
    {
        return $this->buyerAnonymous;
    }
    
    /**
     *
     * @return OneGoTransactionState 
     */
    public static function getCurrent()
    {
        return parent::loadCurrent(new self());
    }
}

class OneGoCompletedOrderState extends OneGoPersistentState
{
    protected $orderId;
    protected $completedOn;
    protected $benefitsApplied;
    protected $buyerEmail;
    protected $newBuyerRegistered;
    protected $prepaidReceived;
    protected $cart;
    protected $transactionState;
    protected $oAuthTokenState;
    protected $transactionDelayed;
    
    protected function getStorageKey()
    {
        return 'CompletedOrderState';
    }
    
    protected function initialize()
    {
        $this->orderId = false;
        $this->completedOn = false;
        $this->benefitsApplied = false;
        $this->buyerEmail = false;
        $this->newBuyerRegistered = false;
        $this->prepaidReceived = false;
        $this->cart = false;
        $this->transactionState = null;
        $this->oAuthTokenState = null;
        $this->transactionDelayed = null;
    }
    
    /**
     *
     * @return OneGoCompletedOrderState 
     */
    public static function getCurrent()
    {
        return parent::loadCurrent(new self());
    }
}

/**
 * Common global usage utilities
 */
class OneGoUtils
{
    const STORAGE_KEY = 'OneGoOpencart';
    
    const LOG_INFO = 0;
    const LOG_NOTICE = 1;
    const LOG_WARNING = 2;
    const LOG_ERROR = 3;

    /**
     * @static
     * @return Registry
     */
    public static function getRegistry()
    {
        global $registry;
        return $registry;
    }
    
    /**
     * @static
     * @return Session 
     */
    public static function getSession()
    {
        $session = self::getRegistry()->get('session');
        return $session;
    }
    
    /**
     * Saves data to registry simulating own namespace
     *
     * @static
     * @param string $key
     * @param mixed $val 
     */
    public static function saveToRegistry($key, $val)
    {
        $registry = self::getRegistry();
        $onego_data = $registry->get(self::STORAGE_KEY);
        if (empty($onego_data)) {
            $onego_data = array();
        }
        $onego_data[$key] = $val;
        $registry->set(self::STORAGE_KEY, $onego_data);
    }
    
    /**
     * Getter from registry namespace
     *
     * @param string $key
     * @return mixed null if no such data is available 
     */
    public static function getFromRegistry($key, $default = null)
    {
        $registry = self::getRegistry();
        $onego_data = $registry->get(self::STORAGE_KEY);
        return isset($onego_data[$key]) ? $onego_data[$key] : $default;
    }
    
    /**
     * Saves data to session under own namespace
     *
     * @param string $key
     * @param mixed $val 
     */
    public static function saveToSession($key, $val)
    {
        $session = self::getSession();
        $session->data[self::STORAGE_KEY][$key] = serialize($val);
    }
    
    /**
     * Namespaced getter from session
     *
     * @param string $key
     * @return mixed 
     */
    public static function getFromSession($key, $default = null)
    {
        $session = self::getSession();
        return isset($session->data[self::STORAGE_KEY][$key]) ? 
                unserialize($session->data[self::STORAGE_KEY][$key]) : $default;
    }

    /**
     * @static
     * @return OneGoAPI_APIConfig
     */
    public static function getAPIConfig()
    {
        $cfg = new OneGoAPI_APIConfig(
                OneGoConfig::get('clientId'),
                OneGoConfig::get('clientSecret'),
                OneGoConfig::get('terminalId'),
                OneGoConfig::get('transactionTTL')*60,
                true,
                OneGoConfig::get('httpConnectionTimeout')
        );
        $cfg->apiUri = OneGoConfig::get('apiURI');
        $cfg->currencyCode = OneGoUtils::getRegistry()->get('config')->get('config_currency');
        return $cfg;
    }
    
    /**
     * Initializes OneGoAPI_Impl_SimpleAPI
     *
     * @return OneGoAPI_Impl_SimpleAPI 
     */
    public static function initAPI()
    {
        $cfg = self::getAPIConfig();
        return OneGoAPI_Impl_SimpleAPI::init($cfg);
    }
    
    /**
     * Initialize OneGoAPI_Impl_SimpleOAuth
     *
     * @return OneGoAPI_Impl_SimpleOAuth 
     */
    public static function initOAuth()
    {
        $cfg = new OneGoAPI_OAuthConfig(
                OneGoConfig::get('clientId'),
                OneGoConfig::get('clientSecret'),
                OneGoConfig::get('authorizationURI'),
                OneGoConfig::get('oAuthURI'),
                OneGoConfig::get('httpConnectionTimeout')
        );
        return OneGoAPI_Impl_SimpleOAuth::init($cfg);
    }

    /**
     * Initialize logging for OneGoAPI_Log
     *
     * @static
     * @return void
     */
    public static function initAPILogging()
    {
        if (OneGoConfig::get('debugModeOn')) {
            OneGoAPI_Log::setLevel(OneGoAPI_Log::DEBUG);
            OneGoAPI_Log::setCallback(array('OneGoUtils', 'logAPICall'));
        }
    }
    
    /**
     *
     * @param string $str
     * @param boolean $strong Whether to escape all characters and not just quotes
     * @return string
     */
    public static function escapeJs($str, $strong = false) {
	$new_str = '';
	$str_len = strlen($str);
	for($i = 0; $i < $str_len; $i++) {
            $char = $str[$i];
            if ($strong || in_array($char, array('\'', '"', "\r", "\n"))) {
                $new_str .= '\\x' . dechex(ord(substr($str, $i, 1)));
            } else {
                $new_str .= $char;
            }
	}
	return $new_str;
    }
    
    /**
     * Determine if current request is an AJAX request
     *
     * @return boolean
     */
    public static function isAjaxRequest()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            ($_SERVER['HTTP_X_REQUESTED_WITH'] == "XMLHttpRequest");
    }
    
    /**
     *
     * @return string HTTP referrer's URL
     */
    public static function getHttpReferer()
    {
        return !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : false;
    }
    
    /**
     * Save log message to session to later display in debugger, truncate log to
     * specified length 
     *
     * @param string $str Log message text
     * @param string $level self::LOG_INFO, self::LOG_NOTICE, self::LOG_WARNING, self::LOG_ERROR
     * @param integer $max_length Max amount of log messages to be saved
     */
    public static function log($str, $level = self::LOG_INFO, $max_length = 25)
    {
        if (OneGoConfig::get('debugModeOn')) {
            $log = self::getLog();
            $log[] = array(
                'time'      => microtime(),
                'pid'       => getmypid(),
                'backtrace' => implode(' / ', self::debugBacktrace()),
                'message'   => $str,
                'level'     => $level,
            );
            $log = array_slice($log, -$max_length); // keep log small
            OneGoUtils::saveToSession('log', $log);
        }
    }
    
    /**
     * Write error to log file
     *
     * @param string $str Error message
     */
    public static function writeLog($str, $addBacktrace = true)
    {
        $fh = fopen(OneGoConfig::get('logFile'), 'a');
        if ($fh) {
            $str = date('Y-m-d H:i:s').' '.$str;
            if ($addBacktrace) {
                $str .= "\n".'--BACKTRACE: '.implode(' / ', self::debugBacktrace());
            }
            fwrite($fh, $str."\n");
            fclose($fh);
        }
    }

    /**
     * Callback method for OneGoAPI_Log::setCallback()
     *
     * @static
     * @param string $message
     * @param string $level
     * @return void
     */
    public static function logAPICall($message, $level)
    {
        self::writeLog($message, false);
    }
    
    /**
     * Log critical errors
     *
     * @param string $errorStr
     * @param Exception $exception 
     */
    public static function logCritical($errorStr, $exception = null)
    {
        if (!empty($exception) && is_a($exception, 'Exception')) {
            $errorStr = $errorStr.' :: '.get_class($exception).' :: '.$exception->getMessage();
        }
        self::log($errorStr, self::LOG_ERROR);
        self::writeLog($errorStr);
    }
    
    /**
     * Returns list of messages saved in log
     *
     * @param boolean $clear Whether to remove returned log messages
     * @return array List of saved log entries
     */
    public static function getLog($clear = false)
    {
        $log = OneGoUtils::getFromSession('log');
        if (empty($log)) {
            $log = array();
        }
        if ($clear) {
            OneGoUtils::saveToSession('log', array());
        }
        return $log;
    }
    
    /**
     * Get backtrace info for debugging purposes, valid for the calling method
     *
     * @param integer $limit Max amount of backtrace steps to be returned
     * @return array List of backtrace data from latest calls to oldest
     */
    public static function debugBacktrace($limit = 5)
    {
        $trace = debug_backtrace();
        $simple = array();
        foreach ($trace as $key => $val) {
            $row = $trace[$key];
            $id = '';
            if (isset($row['class'])) {
                $id .= $row['class'].$row['type'];
            }
            $id .= $row['function'].'()';
            if (isset($row['line'])){
                $id .= ' ['.$row['line'].']';
            }
            $simple[] = $id;
        }
        
        return array_slice($simple, 2, $limit);
    }

    /**
     * Dump variable for debugging
     *
     * @static
     * @param $variable
     * @param string $title
     * @return void
     */
    public static function dbg($variable, $title = false)
    {
        if (OneGoConfig::get('debugModeOn') && !OneGoUtils::isAjaxRequest()) {
            if ($title) {
                echo '<strong>'.$title.':</strong><br />';
            }
            echo '<pre>';
            print_r($variable);
            echo '</pre>';
        }
    }

    /**
     * Recursively convert object to array
     *
     * @static
     * @param $object
     * @param bool $sortProperties
     * @return array
     */
    public static function objectToArray($object, $sortProperties = false)
    {
        $array = get_object_vars($object);
        foreach ($array as $key => $val) {
            if (is_object($val)) {
                $array[$key] = self::objectToArray($val, $sortProperties);
            }
        }
        if ($sortProperties) {
            ksort($array);
        }
        return $array;
    }

    /**
     * Compares Opencart version
     *
     * @static
     * @param string $compareTo
     * @return mixed
     */
    public static function compareVersion($compareTo)
    {
        return version_compare(VERSION, $compareTo);
    }
}

class OneGoTransactionsLog
{
    /**
     * Create DB table for storing OneGo transactions information
     */
    public static function init()
    {
        $db = OneGoUtils::getRegistry()->get('db');
        $sql = "CREATE TABLE IF NOT EXISTS `".DB_PREFIX."onego_transactions_log` (
                  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                  `order_id` int(11) NOT NULL COMMENT 'Opencart order ID',
                  `transaction_id` varchar(100) NOT NULL COMMENT 'OneGo transaction ID',
                  `operation` enum('CONFIRM','CANCEL','DELAY') NOT NULL COMMENT 'OneGo operation',
                  `success` tinyint(1) NOT NULL COMMENT 'Is operation successful',
                  `error_message` text COMMENT 'Error message (optional)',
                  `inserted_on` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                  `expires_in` int(11) DEFAULT NULL COMMENT 'Delayed transaction TTL',
                  PRIMARY KEY (`id`),
                  KEY `order_id` (`order_id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='OneGo transactions for orders'";
        $db->query($sql);
    }
    
    /**
     *
     * @param integer $orderId Opencart order ID
     * @param string $transactionId OneGo transaction ID
     * @param string $operation /transaction/end operation status
     * @param integer $delayTtl Seconds to delay transaction for (for DELAY operations only)
     * @param smallint $failed 1 - operation succeeded, 0 - operation failed
     * @param string $errorMessage
     * @return boolean 
     */
    public static function log(
        $orderId, $transactionId, $operation,
        $delayTtl = null, $failed = false, $errorMessage = null)
    {
        $db = OneGoUtils::getRegistry()->get('db');
        
        $orderId = (int) $orderId;
        $transactionId = $db->escape($transactionId);
        if (!in_array($operation, array(
            OneGoAPI_DTO_TransactionEndDto::STATUS_CONFIRM, 
            OneGoAPI_DTO_TransactionEndDto::STATUS_CANCEL, 
            OneGoAPI_DTO_TransactionEndDto::STATUS_DELAY))) 
        {
            throw new OneGoException('Invalid transaction operation: '.$operation);
        }
        $delayTtl = (int) $delayTtl ? (int) $delayTtl : 'NULL';
        $success = !$failed ? '1' : '0';
        $error = !empty($errorMessage) ? '\''.$db->escape($errorMessage).'\'' : 'NULL';
        
        $sql = "INSERT INTO ".DB_PREFIX."onego_transactions_log
                    (order_id, transaction_id, operation, success, error_message, inserted_on, expires_in)
                VALUES 
                    ({$orderId}, '{$transactionId}', '{$operation}', {$success},
                    {$error}, NOW(), {$delayTtl})";
        $db->query($sql);
        return true;
    }
    
    /**
     *
     * @param integer $orderId Opencart order ID
     * @param mixed $success true - successful operations only, false - failed only, null - all
     * @return array Logged transaction operations, newest first 
     */
    public static function getListForOrder($orderId, $success = null)
    {
        $db = OneGoUtils::getRegistry()->get('db');
        
        if ($success === true) {
            $addwhere = ' AND success=1';
        } else if ($success === false) {
            $addwhere = ' AND success=0';
        } else {
            $addwhere = '';
        }
        $sql = "SELECT * FROM ".DB_PREFIX."onego_transactions_log 
                WHERE order_id={$orderId} {$addwhere}
                ORDER BY inserted_on DESC";
        $res = $db->query($sql);
        $log = array();
        if ($res->num_rows) {
            foreach ($res->rows as $row) {
                $log[] = $row;
            }
        }
        return $log;
    }
    
    /**
     *
     * @param array $ordersIds List of Opencart orders IDs
     * @return array List of last successful operations (or failures) for orders
     */
    public static function getStatusesForOrders($ordersIds)
    {
        $list = array();
        if (!empty($ordersIds)) {
            $db = OneGoUtils::getRegistry()->get('db');
            foreach ($ordersIds as $key => $val) {
                $ordersIds[$key] = (int) $val;
            }
            $ordersIds = array_unique($ordersIds);
            $ids = implode(',', $ordersIds);
            $sql = "SELECT *
                    FROM ".DB_PREFIX."onego_transactions_log otl
                    WHERE otl.order_id IN ({$ids})
                    ORDER BY inserted_on DESC";
            $res = $db->query($sql);
            if (!empty($res->rows)) {
                $statuses = array();
                foreach ($res->rows as $row) {
                    if (!isset($statuses[$row['order_id']]) ||
                        !$statuses[$row['order_id']]['success']) 
                    {
                        $statuses[$row['order_id']] = $row;
                    }
                }
                foreach ($ordersIds as $id) {
                    $list[$id] = isset($statuses[$id]) ? $statuses[$id] : null;
                }
            }
        }
        return $list;
    }
    
    /**
     *
     * @param integer $orderId Opencart order ID
     * @return array Last transaction operation from log
     */
    public static function getStatusForOrder($orderId)
    {
        $statuses = self::getStatusesForOrders(array($orderId));
        return isset($statuses[$orderId]) ? $statuses[$orderId] : false;
    }
}

class OneGoVirtualGiftCards
{
    const STATUS_PENDING = 'PENDING';
    const STATUS_AVAILABLE = 'AVAILABLE';
    const STATUS_USED = 'USED';
    const STATUS_SOLD = 'SOLD';

    public static function init()
    {
        $db = OneGoUtils::getRegistry()->get('db');

        // create DB table to store VGCs
        $sql = "CREATE TABLE IF NOT EXISTS `".DB_PREFIX."onego_vgc_cards` (
                    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `number` varchar(36) NOT NULL COMMENT 'VGC number',
                    `nominal` varchar(36) NOT NULL COMMENT 'VGC nominal',
                    `batch_id` int(11) NULL COMMENT 'VGC batch ID, null if import in progress',
                    `status` enum('PENDING','AVAILABLE','USED','SOLD') NOT NULL COMMENT 'VGC status',
                    `order_id` int(11) NULL COMMENT 'Opencart order ID for VGC sale',
                    `sold_on` timestamp NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `number` (`number`),
                    KEY `order_id` (`order_id`),
                    KEY `batch_id` (`batch_id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='OneGo VGC list'";
        $db->query($sql);

        // create DB table to store VGC batches info
        $sql = "CREATE TABLE IF NOT EXISTS `".DB_PREFIX."onego_vgc_batches` (
                  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                  `nominal` varchar(36) NOT NULL COMMENT 'VGC nominal',
                  `product_id` int(11) NULL COMMENT 'Opencart product ID for selling batch VGCs',
                  `added_on` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `product_id` (`product_id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='OneGo VGC batches'";
        $db->query($sql);
    }

    public static function getPendingCardsCount()
    {
        $db = OneGoUtils::getRegistry()->get('db');
        $sql = "SELECT nominal, COUNT(*) AS cnt
                FROM `".DB_PREFIX."onego_vgc_cards`
                WHERE status='".self::STATUS_PENDING."'
                GROUP BY nominal
                ORDER BY nominal";
        $res = $db->query($sql);
        $counts = array();
        foreach ($res->rows as $row) {
            $counts[$row['nominal']] = $row['cnt'];
        }
        return $counts;
        
    }

    public static function resetPendingCards()
    {
        $db = OneGoUtils::getRegistry()->get('db');
        $sql = "DELETE FROM `".DB_PREFIX."onego_vgc_cards`
                WHERE status='".self::STATUS_PENDING."'";
        $res = $db->query($sql);
    }

    public static function addPendingCard($number, $nominal, $is_active)
    {
        if ($is_active) {
            $db = OneGoUtils::getRegistry()->get('db');
            $number = $db->escape($number);
            $sql = "SELECT number FROM `".DB_PREFIX."onego_vgc_cards`
                    WHERE number='{$number}'";
            $res = $db->query($sql);
            if (!$res->num_rows) {
                $nominal = $db->escape($nominal);
                $status = self::STATUS_PENDING;
                $sql = "INSERT INTO `".DB_PREFIX."onego_vgc_cards`
                        (number, nominal, status)
                        VALUES
                        ('{$number}', '{$nominal}', '{$status}')";
                return $db->query($sql);
            }
        }
        return false;
    }

    public static function createBatch($nominal, $product_id = null)
    {
        $db = OneGoUtils::getRegistry()->get('db');
        $nominal = $db->escape($nominal);
        $product_id = (int) $product_id ? (int) $product_id : 'NULL';
        $sql = "INSERT INTO `".DB_PREFIX."onego_vgc_batches`
                SET nominal='{$nominal}',
                    product_id={$product_id},
                    added_on=NOW()";
        $db->query($sql);
        return $db->getLastId();
    }

    public static function activatePendingCards($batch_id, $nominal)
    {
        $db = OneGoUtils::getRegistry()->get('db');
        $nominal = $db->escape($nominal);
        $batch_id = (int) $batch_id;
        $sql = "UPDATE `".DB_PREFIX."onego_vgc_cards`
                SET batch_id={$batch_id}, status='".self::STATUS_AVAILABLE."'
                WHERE status='".self::STATUS_PENDING."' AND
                    nominal='{$nominal}'";
        $db->query($sql);
    }

    public static function deleteUnsoldCards($product_id)
    {
        $db = OneGoUtils::getRegistry()->get('db');
        $product_id = (int) $product_id;
        $sql = "DELETE FROM `".DB_PREFIX."onego_vgc_cards`
                WHERE batch_id IN (
                    SELECT id FROM `".DB_PREFIX."onego_vgc_batches` WHERE product_id={$product_id}
                ) AND status='".self::STATUS_AVAILABLE."'";
        return $db->query($sql);
    }

    public static function getCardsStock($product_id)
    {
        $db = OneGoUtils::getRegistry()->get('db');
        $product_id = (int) $product_id;
        $sql = "SELECT COUNT(*) AS cnt
                FROM ".DB_PREFIX."onego_vgc_batches b, ".DB_PREFIX."onego_vgc_cards c
                WHERE b.product_id={$product_id} AND b.id=c.batch_id AND c.status='".self::STATUS_AVAILABLE."'";
        $res = $db->query($sql);
        return $res->row['cnt'];
    }
}

class OneGoException extends Exception {}
class OneGoAuthenticationRequiredException extends OneGoException {}
class OneGoVirtualGiftCardNumberInvalidException extends OneGoException {}
class OneGoAPICallFailedException extends OneGoException {}