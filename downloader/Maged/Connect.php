<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Connect
 * @copyright   Copyright (c) 2010 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

error_reporting(E_ALL & ~E_NOTICE);

// just a shortcut
if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

// add Mage lib in include_path if needed
$_includePath = get_include_path();
$_libDir = dirname(dirname(__FILE__)) . DS . 'lib';
if (strpos($_includePath, $_libDir) === false) {
    if (substr($_includePath, 0, 2) === '.' . PATH_SEPARATOR) {
        $_includePath = '.' . PATH_SEPARATOR . $_libDir . PATH_SEPARATOR . substr($_includePath, 2);
    } else {
        $_includePath = $_libDir . PATH_SEPARATOR . $_includePath;
    }
    set_include_path($_includePath);
}

/**
* Class for connect
*
* @category   Mage
* @package    Mage_Connect
* @copyright  Copyright (c) 2009 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
* @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*/
class Maged_Connect
{

    /**
    * Object of config
    *
    * @var Mage_Connect_Config
    */
    protected $_config;

    /**
    * Object of single config
    *
    * @var Mage_Connect_Singleconfig
    */
    protected $_sconfig;

    /**
    * Object of frontend
    *
    * @var Mage_Connect_Frontend
    */
    protected $_frontend;

    /**
    * Internal cache for command objects
    *
    * @var array
    */
    protected $_cmdCache = array();

    /**
    * Instance of class
    *
    * @var Maged_Connect
    */
    static protected $_instance;

    /**
    * Constructor
    */
    public function __construct()
    {
        $this->getConfig();
        $this->getSingleConfig();
        $this->getFrontend();
    }

    /**
    * Initialize instance
    *
    * @return Maged_Connect
    */
    public static function getInstance()
    {
        if (!self::$_instance) {
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    /**
    * Retrieve object of config and set it to Mage_Connect_Command
    *
    * @return Mage_Connect_Config
    */
    public function getConfig()
    {
        if (!$this->_config) {
            $this->_config = new Mage_Connect_Config();
            $ftp=$this->_config->__get('remote_config');
            if(!empty($ftp)){
                $packager = new Mage_Connect_Packager();
                list($cache, $config, $ftpObj) = $packager->getRemoteConf($ftp);
                $this->_config=$config;
                $this->_sconfig=$cache;
            }
            $this->_config->magento_root = dirname(dirname(__FILE__)).DS.'..';
            Mage_Connect_Command::setConfigObject($this->_config);
        }
        return $this->_config;
    }

    /**
    * Retrieve object of single config and set it to Mage_Connect_Command
    *
    * @param bool $reload
    * @return Mage_Connect_Singleconfig
    */
    public function getSingleConfig($reload = false)
    {
        if(!$this->_sconfig || $reload) {
            $this->_sconfig = new Mage_Connect_Singleconfig($this->getConfig()->magento_root . DIRECTORY_SEPARATOR . $this->getConfig()->downloader_path . DIRECTORY_SEPARATOR . Mage_Connect_Singleconfig::DEFAULT_SCONFIG_FILENAME);
        }
        Mage_Connect_Command::setSconfig($this->_sconfig);
        return $this->_sconfig;

    }

    /**
    * Retrieve object of frontend and set it to Mage_Connect_Command
    *
    * @return Maged_Connect_Frontend
    */
    public function getFrontend()
    {
        if (!$this->_frontend) {
            $this->_frontend = new Maged_Connect_Frontend();
            Mage_Connect_Command::setFrontendObject($this->_frontend);
        }
        return $this->_frontend;
    }

    /**
    * Retrieve lof from frontend
    *
    * @return array
    */
    public function getLog()
    {
        return $this->getFrontend()->getLog();
    }

    /**
    * Retrieve output from frontend
    *
    * @return array
    */
    public function getOutput()
    {
        return $this->getFrontend()->getOutput();
    }

    /**
    * Clean registry
    *
    * @return Maged_Connect
    */
    public function cleanSconfig()
    {
        $this->getSingleConfig()->clear();
        return $this;
    }

    /**
    * Delete directory recursively
    *
    * @param string $path
    * @return Maged_Connect
    */
    public function delTree($path) {
        if (@is_dir($path)) {
            $entries = @scandir($path);
            foreach ($entries as $entry) {
                if ($entry != '.' && $entry != '..') {
                    $this->delTree($path.DS.$entry);
                }
            }
            @rmdir($path);
        } else {
            @unlink($path);
        }
        return $this;
    }

    /**
    * Run commands from Mage_Connect_Command
    *
    * @param string $command
    * @param array $options
    * @param array $params
    * @return
    */
    public function run($command, $options=array(), $params=array())
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '256M');

        if (empty($this->_cmdCache[$command])) {
            Mage_Connect_Command::getCommands();
            /**
            * @var $cmd Mage_Connect_Command
            */
            $cmd = Mage_Connect_Command::getInstance($command);
            if ($cmd instanceof Mage_Connect_Error) {
                return $cmd;
            }
            $this->_cmdCache[$command] = $cmd;
        } else {
            /**
            * @var $cmd Mage_Connect_Command
            */
            $cmd = $this->_cmdCache[$command];
        }
        $ftp=$this->getConfig()->remote_config;
        if(strlen($ftp)>0){
            $options=array_merge($options, array('ftp'=>$ftp));
        }
        $cmd->run($command, $options, $params);
        if ($cmd->ui()->hasErrors()) {
            return false;
        } else {
            return true;
        }
    }

    public function setRemoteConfig($uri) #$host, $user, $password, $path='', $port=null)
    {
        #$uri = 'ftp://' . $user . ':' . $password . '@' . $host . (is_numeric($port) ? ':' . $port : '') . '/' . trim($path, '/') . '/';
        //$this->run('config-set', array(), array('remote_config', $uri));
        //$this->run('config-set', array('ftp'=>$uri), array('remote_config', $uri));
        $this->getConfig()->remote_config=$uri;
        return $this;
    }

    /**
     *
     * @param array $errors Error messages
     * @return Maged_Connect
     */
    public function showConnectErrors($errors)
    {
        echo '<script type="text/javascript">';
        $run = new Maged_Model_Connect_Request();
        if ($callback = $run->get('failure_callback')) {
            if (is_array($callback)) {
                call_user_func_array($callback, array($result));
            } else {
                echo $callback;
            }
        }
        echo '</script>';

        return $this;
    }

    /**
     * Run Mage_COnnect_Command with html output console style
     *
     * @param array|Maged_Model $runParams command, options, params,
     *        comment, success_callback, failure_callback
     */
    public function runHtmlConsole($runParams)
    {
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);
        for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
        ob_implicit_flush();

        $fe = $this->getFrontend();
        $oldLogStream = $fe->getLogStream();
        $fe->setLogStream('stdout');

        if ($runParams instanceof Maged_Model) {
            $run = $runParams;
        } elseif (is_array($runParams)) {
            $run = new Maged_Model_Connect_Request($runParams);
        } elseif (is_string($runParams)) {
            $run = new Maged_Model_Connect_Request(array('comment'=>$runParams));
        } else {
            throw Maged_Exception("Invalid run parameters");
        }

        if (!$run->get('no-header')) {
?>
<html><head><style type="text/css">
body { margin:0px;
    padding:3px;
    background:black;
    color:#2EC029;
    font:normal 11px Lucida Console, Courier New, serif;
    }
</style></head><body>
<script type="text/javascript">
if (parent && parent.disableInputs) {
    parent.disableInputs(true);
}
if (typeof auto_scroll=='undefined') {
    var auto_scroll = window.setInterval(console_scroll, 10);
}
function console_scroll()
{
    if (typeof top.$!='function') {
        return;
    }
    if (top.$('connect_iframe_scroll').checked) {
        document.body.scrollTop+=3;
    }
}
</script>
<?php
        }
        echo htmlspecialchars($run->get('comment'));

        if ($command = $run->get('command')) {
            $result = $this->run($command, $run->get('options'), $run->get('params'));

            if ($this->getFrontend()->hasErrors()) {
                echo "<br/>CONNECT ERROR: ";
                foreach ($this->getFrontend()->getErrors(false) as $error) {
                    echo nl2br($error[1]);
                    echo '<br/>';
                }
            }
            echo '<script type="text/javascript">';
            if ($this->getFrontend()->hasErrors()) {
                if ($callback = $run->get('failure_callback')) {
                    if (is_array($callback)) {
                        call_user_func_array($callback, array($result));
                    } else {
                        echo $callback;
                    }
                }
            } else {
                if (!$run->get('no-footer')) {
                    if ($callback = $run->get('success_callback')) {
                        if (is_array($callback)) {
                            call_user_func_array($callback, array($result));
                        } else {
                            echo $callback;
                        }
                    }
                }
            }
            echo '</script>';
        } else {
            $result = false;
        }
        if ($this->getFrontend()->getErrors() || !$run->get('no-footer')) {
?>
<script type="text/javascript">
if (parent && parent.disableInputs) {
    parent.disableInputs(false);
}
</script>
</body></html>
<?php
            $fe->setLogStream($oldLogStream);
        }
        return $result;
    }
}
