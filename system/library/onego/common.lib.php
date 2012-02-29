<?php
require_once 'php-api/src/OneGoAPI/init.php';

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
    
    public function get($key)
    {
        $val = $this->config->get('onego_'.$key);
        if (!is_null($val)) {
            return $val;
        }
        if (isset($this->onegoConfig[$key])) {
            return $this->onegoConfig[$key];
        }
        return null;
    }
    
    public function getArray($key)
    {
        $val = $this->get($key);
        if (!is_null($val) && !is_array($val)) {
            $val = explode('|', $val);
        }
        return $val;
    }
}

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
    
    public function toArray()
    {
        $arr = get_object_vars($this);
        foreach ($arr as $key => $val){
            if (is_a($val, 'OneGoPersistentState')) {
                $arr[$key] = $val->toArray();
            }
        }
        return $arr;
    }
    
    protected function save()
    {
        OneGoUtils::saveToSession($this->getStorageKey(), $this);
    }
    
    /**
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


class OneGoUtils
{
    const STORAGE_KEY = 'OneGoOpencart';
    
    const LOG_INFO = 0;
    const LOG_NOTICE = 1;
    const LOG_WARNING = 2;
    const LOG_ERROR = 3;
    
    public static function getRegistry()
    {
        global $registry;
        return $registry;
    }
    
    /**
     *
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
     * Initializes OneGoAPI_Impl_SimpleAPI
     *
     * @return OneGoAPI_Impl_SimpleAPI 
     */
    public static function initAPI()
    {
        $cfg = new OneGoAPI_APIConfig(
                OneGoConfig::getInstance()->get('clientId'),
                OneGoConfig::getInstance()->get('clientSecret'), 
                OneGoConfig::getInstance()->get('terminalId'), 
                OneGoConfig::getInstance()->get('transactionTTL')*60,
                true,
                OneGoConfig::getInstance()->get('httpConnectionTimeout')
        );
        $cfg->apiUri = OneGoConfig::getInstance()->get('apiURI');
        $cfg->currencyCode = OneGoUtils::getRegistry()->get('config')->get('config_currency');
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
                OneGoConfig::getInstance()->get('clientId'), 
                OneGoConfig::getInstance()->get('clientSecret'), 
                OneGoConfig::getInstance()->get('authorizationURI'), 
                OneGoConfig::getInstance()->get('oAuthURI'),
                OneGoConfig::getInstance()->get('httpConnectionTimeout')
        );
        return OneGoAPI_Impl_SimpleOAuth::init($cfg);
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
        if (OneGoConfig::getInstance()->get('debugModeOn')) {
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
    public static function writeLog($str)
    {
        $fh = fopen(DIR_LOGS.'onego_error.log', 'a');
        if ($fh) {
            $ln = date('Y-m-d H:i:s').' '.$str.' => '.implode(' / ', self::debugBacktrace());
            fwrite($fh, $ln."\n");
            fclose($fh);
        }
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
}

class OneGoException extends Exception {}
class OneGoAuthenticationRequiredException extends OneGoException {}
class OneGoVirtualGiftCardNumberInvalidException extends OneGoException {}
class OneGoAPICallFailedException extends OneGoException {}