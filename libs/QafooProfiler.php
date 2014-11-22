<?php
namespace QafooLabs\Profiler;
/**
 * QafooLabs Profiler
 *
 * LICENSE
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file LICENSE.txt.
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to kontakt@beberlei.de so I can send you a copy immediately.
 */


/**
 * Low-level abstraction for storage of profiling data.
 */
interface Backend
{
    public function storeProfile(array $data);
    public function storeMeasurement(array $data);
    public function storeError(array $data);
}


class CurlBackend implements Backend
{
    private $certificationFile;
    private $connectionTimeout;
    private $timeout;

    public function __construct($certificationFile = null, $connectionTimeout = 3, $timeout = 3)
    {
        $this->certificationFile = $certificationFile;
        $this->connectionTimeout = $connectionTimeout;
        $this->timeout = $timeout;
    }

    public function storeProfile(array $data)
    {
        if (function_exists("curl_init") === false) {
            return;
        }

        $data['server'] = gethostname();

        $this->request(
            "https://profiler.qafoolabs.com/api/profile/create",
            $data,
            $data['apiKey'],
            $data['op']
        );
    }

    public function storeMeasurement(array $data)
    {
        $this->request("https://profiler.qafoolabs.com/api/performance", array(
            'server' => gethostname(),
            'time' => time(),
            'apps' => array(
                $data['apiKey'] => array(
                    'operations' => array(
                        $data['op'] => array(
                            'lastRev' => time()+84600,
                            'cnt' => 1,
                            'err' => 0,
                            'wt' => array(
                                array(
                                    'wt' => $data['wt'],
                                    'mem' => $data['mem'],
                                    'c' => $data['c'],
                                )
                            )
                        )
                    )
                )
            )
        ));
    }

    public function storeError(array $data)
    {
    }

    private function request($url, $data, $apiKey = null, $operation = null)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectionTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($this->certificationFile) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_CAINFO, $this->certificationFile);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $headers = array(
            "Content-Type: application/json+gzip",
            "User-Agent: QafooLabs Profiler Collector DevMode"
        );

        if ($apiKey) {
            $headers[] = 'X-Profiler-Key: ' . $apiKey;
        }
        if ($operation) {
            $headers[] = 'X-Profiler-Op: ' . $operation;
        }

        curl_setopt($ch, CURLOPT_FAILONERROR,true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, gzencode(json_encode($data)));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (curl_exec($ch) === false) {
            syslog(LOG_WARNING, "Qafoo Profiler DevMode cURL failed: " . curl_error($ch));
        }
    }
}
/**
 * QafooLabs Profiler
 *
 * LICENSE
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file LICENSE.txt.
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to kontakt@beberlei.de so I can send you a copy immediately.
 */


class NetworkBackend implements Backend
{
    const TYPE_PROFILE = 'profile';
    const TYPE_ERROR = 'error';

    public function storeProfile(array $data)
    {
        $this->storeThroughFileSocket(self::TYPE_PROFILE, $data);
    }

    private function storeThroughFileSocket($dataType, array $data)
    {
        $fp = @stream_socket_client("unix:///tmp/qprofd.sock");

        if ($fp == false) {
            return;
        }

        stream_set_timeout($fp, 0, 10000); // 10 milliseconds max
        @fwrite($fp, json_encode(array('type' => $dataType, 'payload' => $data)));
        fclose($fp);
    }

    public function storeMeasurement(array $data)
    {
        $fp = @stream_socket_client("udp://127.0.0.1:8135");

        if ($fp == false) {
            return;
        }

        stream_set_timeout($fp, 0, 200);
        @fwrite($fp, json_encode($data, JSON_FORCE_OBJECT));
        fclose($fp);
    }

    public function storeError(array $data)
    {
        $this->storeThroughFileSocket(self::TYPE_ERROR, $data);
    }
}
/**
 * Qafoo Profiler Client
 *
 * LICENSE
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file LICENSE.txt.
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to kontakt@beberlei.de so I can send you a copy immediately.
 */


/**
 * Anonymize SQL statements.
 *
 * To avoid passing private data to the profiler services you have to anonymize
 * SQL statements that are collected as custom timers.
 *
 * The class detects all strings and numbers in sql statements and replaces
 * them with question marks (?).
 *
 * If your library such as Doctrine DBAL only uses prepared statements anyways
 * and you don't put user input into SQL yourself, then you can skip this step.
 *
 * @TODO: Rename to something more meaningful, since it also anonymizes messages.
 */
class SqlAnonymizer
{
    /**
     * @var string
     */
    const SPLIT_NUMBERS_AND_QUOTED_STRINGS = '(("[^"]+"|\'[^\']+\'|([0-9]*\.)?[0-9]+))';

    /**
     * Anonymize SQL string.
     *
     * Splits a SQL at quoted literals and numbers, replacing them with
     * question marks for anonymization.
     *
     * @param string $sql
     * @return string
     */
    static public function anonymize($sql)
    {
        return preg_replace(self::SPLIT_NUMBERS_AND_QUOTED_STRINGS, '?', $sql);
    }
}
/**
 * QafooLabs Profiler
 *
 * LICENSE
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file LICENSE.txt.
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to kontakt@beberlei.de so I can send you a copy immediately.
 */

namespace QafooLabs;

/**
 * QafooLabs Profiler PHP API
 *
 * Contains all methods to gather measurements and profile data with
 * Xhprof and send to local Profiler Collector Daemon.
 *
 * This class is intentionally monolithic and static to allow
 * users to easily copy it into their projects or auto-prepend PHP
 * scripts.
 *
 * @example
 *
 *      QafooLabs\Profiler::start($apiKey);
 *      QafooLabs\Profiler::setTransactionName("my tx name");
 *
 * Calling the {@link stop()} method is not necessary as it is
 * called automatically from a shutdown handler, if you are timing
 * worker processes however it is necessary:
 *
 *      QafooLabs\Profiler::stop();
 *
 * The method {@link setTransactionName} is required, failing to call
 * it will result in discarding of the data. You can automatically
 * guess a name using the following snippet:
 *
 *      QafooLabs\Profiler::useRequestAsTransactionName();
 *
 * If you want to collect custom timing data you can use for SQL:
 *
 *      $sql = "SELECT 1";
 *      $id = QafooLabs\Profiler::startSqlCustomTimer($sql);
 *      mysql_query($sql);
 *      QafooLabs\Profiler::stopCustomTimer($id);
 *
 * Or for any other timing data:
 *
 *      $id = QafooLabs\Profiler::startCustomTimer('solr', 'q=foo');
 *      QafooLabs\Profiler::stopCustomTimer($id);
 */
class Profiler
{
    const EXT_FATAL            = 1;
    const EXT_LAYERS           = 2;
    const EXT_EXCEPTION        = 4;
    const EXT_TRANSACTION_NAME = 8;

    const FRAMEWORK_ZEND_FRAMEWORK1 = 'Zend_Controller_Action::dispatch';
    const FRAMEWORK_ZEND_FRAMEWORK2 = 'Zend\\MVC\\Controller\\ControllerManager::get';
    const FRAMEWORK_SYMFONY2_COMPONENT = 'Symfony\Component\HttpKernel\Controller\ControllerResolver::createController';
    const FRAMEWORK_SYMFONY2_FRAMEWORK = 'Symfony\Bundle\FrameworkBundle\Controller\ControllerResolver::createController';
    const FRAMEWORK_OXID = 'oxView::setClassName';
    const FRAMEWORK_SHOPWARE = 'Enlight_Controller_Action::dispatch';
    const FRAMEWORK_WORDPRESS = 'get_query_template';

    private static $framework;
    private static $apiKey;
    private static $started = false;
    private static $shutdownRegistered = false;
    private static $operationName;
    private static $customVars;
    private static $customTimers;
    private static $customTimerCount = 0;
    private static $error;
    private static $profiling = false;
    private static $sampling = false;
    private static $correlationId;
    private static $backend;
    private static $uid;
    private static $extensionPrefix;
    private static $extensionFlags = 0;

    private static function getDefaultArgumentFunctions()
    {
        $argumentFunctions = array(
            'PDOStatement::execute',
            'PDO::exec',
            'PDO::query',
            'mysql_query',
            'mysqli_query',
            'mysqli::query',
            'curl_exec',
            'Twig_Template::render',
        );

        if (version_compare(phpversion("qafooprofiler"), "1.2.2") >= 0) {
            $argumentFunctions[] = 'Smarty::fetch';
            $argumentFunctions[] = 'Smarty_Internal_TemplateBase::fetch';
        }

        return $argumentFunctions;
    }

    private static function getDefaultLayerFunctions()
    {
        return array(
            'PDO::__construct' => 'db',
            'PDO::exec' => 'db',
            'PDO::query' => 'db',
            'PDO::commit' => 'db',
            'PDOStatement::execute' => 'db',
            'mysql_query' => 'db',
            'mysqli_query' => 'db',
            'mysqli::query' => 'db',
            'curl_exec' => 'http',
            'curl_multi_exec' => 'http',
            'curl_multi_select' => 'http',
            'file_get_contents' => 'io',
            'file_put_contents' => 'io',
            'fopen' => 'io',
            'fsockopen' => 'io',
            'fgets' => 'io',
            'fputs' => 'io',
            'fwrite' => 'io',
            'file_exists' => 'io',
            'MemcachePool::get' => 'cache',
            'MemcachePool::set' => 'cache',
            'Memcache::connect' => 'cache',
            'apc_fetch' => 'cache',
            'apc_store' => 'cache',
        );
    }

    public static function setBackend(Profiler\Backend $backend)
    {
        self::$backend = $backend;
    }

    /**
     * Instruct Qafoo Profiler to automatically detect transaction names during profiling.
     *
     * @param string $framework one of the QafooLabs\Profiler::FRAMEWORK_* constants.
     */
    public static function detectFrameworkTransaction($framework)
    {
        if (extension_loaded('qafooprofiler')) {
            self::$framework = $framework;
        }
    }

    /**
     * Start profiling in development mode.
     *
     * This will always generate a full profile and send it to the profiler.
     *
     * WARNING: This method can cause huge performance impact on production
     * setups.
     */
    public static function startDevelopment($apiKey, array $options = array())
    {
        self::start($apiKey, 100, $options);
    }

    /**
     * Start production profiling for the application.
     *
     * There are three modes for profiling:
     *
     * 1. Wall-time only profiling of the complete request (no overhead)
     * 2. Full profile/trace using xhprof (depending of # function calls
     *    significant overhead)
     * 3. Whitelist-profiling mode only interesting functions.
     *    (5-40% overhead, requires custom xhprof version >= 0.95)
     *
     * Decisions to profile are made based on a sample-rate and random picks.
     * You can influence the sample rate and pick a value that suites your
     * application. Applications with lower request rates need a much higher
     * transaction rate (25-50%) than applications with high load (<= 1%).
     *
     * Factors that influence sample rate:
     *
     * 1. Second parameter $sampleRate to start() method.
     * 2. _qprofiler Query Parameter (string key is deprecated or array)
     * 3. Cookie QAFOO_PROFILER_SESSION
     * 4. QAFOO_PROFILER_SAMPLERATE environment variable.
     *
     * start() automatically invokes a register shutdown handler that stops and
     * transmits the profiling data to the local daemon for further processing.
     *
     * @param string            $apiKey Application key can be found in "Settings" tab of Profiler UI
     * @param int               $sampleRate Sample rate in full percent (1= 1%, 20 = 20%). Defaults to every fifth request
     * @param array             $options XHProf options.
     *
     * @return void
     */
    public static function start($apiKey, $sampleRate = 20, array $options = array())
    {
        if (self::$started) {
            return;
        }

        if (strlen($apiKey) === 0) {
            return;
        }

        if (isset($_SERVER['QAFOO_PROFILER_DISABLED']) && $_SERVER['QAFOO_PROFILER_DISABLED']) {
            return;
        }

        self::init($apiKey);

        if (!self::$extensionPrefix) {
            return;
        }

        $sampleRate = isset($_SERVER['QAFOO_PROFILER_SAMPLERATE']) ? intval($_SERVER['QAFOO_PROFILER_SAMPLERATE']) : $sampleRate;
        $flags = isset($_SERVER['QAFOO_PROFILER_FLAGS']) ? intval($_SERVER['QAFOO_PROFILER_FLAGS']) : 0;

        self::$profiling = self::decideProfiling($sampleRate);

        if (self::$profiling == true) {
            if (!isset($_SERVER['QAFOO_PROFILER_ENABLE_ARGUMENTS']) || $_SERVER['QAFOO_PROFILER_ENABLE_ARGUMENTS'] == true) {
                if (!isset($options['argument_functions'])) {
                    $options['argument_functions'] = self::getDefaultArgumentFunctions();
                }
            }

            if (self::$framework) {
                $options['transaction_function'] = self::$framework;
            }

            $enable = self::$extensionPrefix . '_enable';
            $enable($flags, $options); // full profiling mode
            return;
        }

        if ((self::$extensionFlags & self::EXT_LAYERS) === 0) {
            return;
        }

        if (!isset($_SERVER['QAFOO_PROFILER_ENABLE_LAYERS']) || !$_SERVER['QAFOO_PROFILER_ENABLE_LAYERS']) {
            $options['layers'] = array();
        } else if (!isset($options['layers'])) {
            $options['layers'] = self::getDefaultLayerFunctions();
        }

        if (!$options['layers'] && !self::$framework) {
            return;
        }

        qafooprofiler_layers_enable($options['layers'], self::$framework);

        self::$sampling = true;
    }

    private static function decideProfiling($treshold)
    {
        // Deprecated method, will be removed soon
        if (isset($_GET['_qprofiler']) && is_string($_GET['_qprofiler']) && $_GET['_qprofiler'] === md5(self::$apiKey)) {
            self::$correlationId = 'dev-force'; // make sure Daemon keeps the profile.

            if (isset($_GET['_qpm']) && $_GET['_qpm'] === 'curl') {
                self::setBackend(new Profiler\CurlBackend());
            }

            return true;
        }

        $vars = array();

        if (isset($_SERVER['HTTP_X_QAFOO_PROFILER']) && is_string($_SERVER['HTTP_X_QAFOO_PROFILER'])) {
            parse_str($_SERVER['HTTP_X_QAFOO_PROFILER'], $vars);
        } else if (isset($_SERVER['QAFOO_PROFILER_SESSION']) && is_string($_SERVER['QAFOO_PROFILER_SESSION'])) {
            parse_str($_SERVER['QAFOO_PROFILER_SESSION'], $vars);
        } else if (isset($_COOKIE['QAFOO_PROFILER_SESSION']) && is_string($_COOKIE['QAFOO_PROFILER_SESSION'])) {
            parse_str($_COOKIE['QAFOO_PROFILER_SESSION'], $vars);
        } else if (isset($_GET['_qprofiler']) && is_array($_GET['_qprofiler'])) {
            $vars = $_GET['_qprofiler'];
        }

        if (isset($_SERVER['QAFOO_PROFILER_DISABLE_SESSIONS']) && $_SERVER['QAFOO_PROFILER_DISABLE_SESSIONS']) {
            $vars = array();
        }

        if (isset($vars['hash'], $vars['time'], $vars['user'], $vars['method'])) {
            $message = 'method=' . $vars['method'] . '&time=' . $vars['time'] . '&user=' . $vars['user'];

            if ($vars['time'] > time() && hash_hmac('sha256', $message, md5(self::$apiKey)) === $vars['hash']) {
                if ($vars['method'] === 'curl') {
                    self::setBackend(new Profiler\CurlBackend());
                }

                self::$correlationId = 'dev-user-' . $vars['user'];

                return true;
            }
        }

        $rand = rand(1, 100);

        return $rand <= $treshold;
    }

    /**
     * Ignore this transaction and don't collect profiling or performance measurements.
     *
     * @return void
     */
    public static function ignoreTransaction()
    {
        if (self::$started && (self::$profiling || self::$sampling)) {
            self::$profiling = false;
            self::$sampling = false;

            $disable = self::$extensionPrefix . '_disable';
            $disable();
        }

        self::$started = false;
    }

    private static function init($apiKey)
    {
        if (self::$shutdownRegistered == false) {
            register_shutdown_function(array("QafooLabs\\Profiler", "shutdown"));
            self::$shutdownRegistered = true;
        }

        if (self::$backend === null) {
            self::$backend = new Profiler\NetworkBackend();
        }

        self::$profiling = false;
        self::$sampling = false;
        self::$apiKey = $apiKey;
        self::$customVars = array();
        self::$customTimers = array();
        self::$customTimerCount = 0;
        self::$operationName = 'default';
        self::$error = false;
        self::$started = microtime(true);
        self::$uid = null;

        if (function_exists('qafooprofiler_enable')) {
            $version = phpversion('qafooprofiler');

            self::$extensionPrefix = 'qafooprofiler';
            self::$extensionFlags = (self::EXT_EXCEPTION | self::EXT_FATAL | self::EXT_TRANSACTION_NAME);
            self::$extensionFlags |= (version_compare($version, "1.2.2") >= 0) ? self::EXT_LAYERS : 0;
            self::$customVars['xhpv'] = 'qp-' . $version;
        } else if (function_exists('xhprof_enable')) {
            self::$extensionPrefix = 'xhprof';
            self::$customVars['xhpv'] = 'xhp-' . phpversion('xhprof');
        } else if (function_exists('uprofiler_enable')) {
            self::$extensionPrefix = 'uprofiler';
            self::$customVars['xhpv'] = 'up-' . phpversion('uprofiler');
        }
    }

    /**
     * Return transaction hash for real user monitoring.
     *
     * @return string
     */
    public static function getTransactionHash()
    {
        return substr(sha1(self::$operationName), 0, 12);
    }

    /**
     * Return api hash used for real user monitoring.
     *
     * @return string
     */
    public static function getApiHash()
    {
        return sha1(self::$apiKey);
    }

    public static function setCorrelationId($id)
    {
        self::$correlationId = $id;
    }

    public static function setTransactionName($name)
    {
        self::$operationName = !empty($name) ? $name : 'empty';
    }

    /**
     * Use {@link setTransactionName} instead.
     *
     * @deprecated
     */
    public static function setOperationName($name)
    {
        self::$operationName = $name;
    }

    /**
     * Start a custom timer for SQL execution with the give SQL query.
     *
     * Returns the timer id to be passed to {@link stopCustomTimer()}
     * for completing the timing process. Queries passed to this
     * method are anonymized using {@link SqlAnonymizer::anonymize()}.
     *
     * @param string $query
     * @return integer|bool
     */
    public static function startSqlCustomTimer($query)
    {
        return self::startCustomTimer('sql', Profiler\SqlAnonymizer::anonymize($query));
    }

    /**
     * Start a custom timer for the given group and using the given description.
     *
     * Data passed as description it not anonymized. It is your responsibility to
     * strip the data of any input that might cause private data to be sent to
     * Qafoo Profiler service.
     *
     * @param string $group
     * @param string $description
     * @return integer|bool
     */
    public static function startCustomTimer($group, $description)
    {
        if (self::$started == false || self::$profiling == false) {
            return false;
        }

        self::$customTimers[self::$customTimerCount] = array("s" => microtime(true), "id" => $description, "group" => $group);
        self::$customTimerCount++;

        return self::$customTimerCount - 1;
    }

    /**
     * Stop the custom timer with given id.
     *
     * @return bool
     */
    public static function stopCustomTimer($id)
    {
        if ($id === false || !isset(self::$customTimers[$id]) || isset(self::$customTimers[$id]["wt"])) {
            return false;
        }

        self::$customTimers[$id]["wt"] = intval(round((microtime(true) - self::$customTimers[$id]["s"]) * 1000000));
        unset(self::$customTimers[$id]["s"]);
        return true;
    }

    /**
     * Return all current custom timers.
     *
     * @return array
     */
    public static function getCustomTimers()
    {
        return self::$customTimers;
    }

    public static function isStarted()
    {
        return self::$started !== false;
    }

    public static function isProfiling()
    {
        return self::$profiling;
    }

    /**
     * Add a custom variable to this profile.
     *
     * Examples are the Request URL, UserId, Correlation Ids and more.
     *
     * Please do *NOT* set private data in custom variables as this
     * data is not encrypted on our servers.
     *
     * Only accepts scalar values.
     *
     * The key 'url' is a magic value and should contain the request
     * url if you want to transmit it. The Profiler UI will specially
     * display it.
     *
     * @param string $name
     * @param scalar $value
     * @return void
     */
    public static function setCustomVariable($name, $value)
    {
        if (!self::$profiling || !is_scalar($value)) {
            return;
        }

        self::$customVars[$name] = $value;
    }

    public static function getCustomVariable($name)
    {
        return isset(self::$customVars[$name])
            ? self::$customVars[$name]
            : null;
    }

    /**
     * Stop all profiling actions and submit collected data.
     */
    public static function stop()
    {
        if (self::$started == false) {
            return;
        }

        $data = null;
        $sampling = self::$sampling;

        if (self::$operationName === 'default' && (self::$extensionFlags & self::EXT_TRANSACTION_NAME) > 0) {
            self::$operationName = qafooprofiler_transaction_name() ?: 'default';
        }

        if (self::$sampling || self::$profiling) {
            $disable = self::$extensionPrefix . '_disable';
            $data = $disable();
        }

        $duration = intval(round((microtime(true) - self::$started) * 1000));

        self::$started = false;
        self::$profiling = false;
        self::$sampling = false;

        if (self::$error) {
            self::storeError(self::$operationName, self::$error, $duration);
        }

        if (!self::$operationName) {
            return;
        }

        if (!$sampling && $data) {
            self::storeProfile(self::$operationName, $data, self::$customTimers);
        } else {
            $callData = $sampling ? $data : array();

            self::storeMeasurement(
                self::$operationName,
                $duration,
                $callData,
                (self::$error !== false)
            );
        }
    }

    private static function storeError($operationName, $errorData, $duration)
    {
        self::$backend->storeError(
            array(
                "op" => ($operationName === null ? '__unknown__' : $operationName),
                "error" => $errorData,
                "apiKey" => self::$apiKey,
                "wt" => $duration,
                "cid" => (string)self::$correlationId
            )
        );
    }

    private static function storeProfile($operationName, $data, $customTimers)
    {
        if (!isset($data["main()"]["wt"]) || !$data["main()"]["wt"]) {
            return;
        }

        if (isset($_SERVER['REQUEST_METHOD']) && !isset(self::$customVars['method'])) {
            self::$customVars['method'] = $_SERVER["REQUEST_METHOD"];
        }

        if (isset($_SERVER['REQUEST_URI']) && !isset(self::$customVars['url'])) {
            self::$customVars['url'] = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . self::getRequestUri();
        }

        self::$backend->storeProfile(array(
            "uid" => self::getProfileTraceUuid(),
            "op" => $operationName,
            "data" => $data,
            "custom" => $customTimers,
            "vars" => self::$customVars ?: null,
            "apiKey" => self::$apiKey,
            "mem" => round(memory_get_peak_usage() / 1024),
            "cid" => (string)self::$correlationId,
        ));
    }

    private static function storeMeasurement($operationName, $duration, array $callData, $isError)
    {
        self::$backend->storeMeasurement(array(
            "op" => $operationName,
            "wt" => $duration,
            "mem" => round(memory_get_peak_usage() / 1024),
            "apiKey" => self::$apiKey,
            "c" => $callData,
            "err" => $isError,
        ));
    }

    /**
     * Use Request or Script information for the transaction name.
     *
     * @return void
     */
    public static function useRequestAsTransactionName()
    {
        self::setTransactionName(self::guessOperationName());
    }

    /**
     * Use {@link useRequestAsTransactionName()} instead.
     *
     * @deprecated
     */
    public static function guessOperationName()
    {
        if (php_sapi_name() === "cli") {
            return basename($_SERVER["argv"][0]);
        }

        return $_SERVER["REQUEST_METHOD"] . " " . self::getRequestUri();
    }

    protected static function getRequestUri()
    {
        return strpos($_SERVER["REQUEST_URI"], "?")
            ? substr($_SERVER["REQUEST_URI"], 0, strpos($_SERVER["REQUEST_URI"], "?"))
            : $_SERVER["REQUEST_URI"];
    }

    public static function logFatal($message, $file, $line, $type = null, $trace = null)
    {
        if (self::$error) {
            return;
        }

        if ($type === null) {
            $type = E_USER_ERROR;
        }

        $message = Profiler\SqlAnonymizer::anonymize($message);

        self::$error = array(
            "message" => $message,
            "file" => $file,
            "line" => $line,
            "type" => $type,
            "trace" => ($trace && is_array($trace)) ? self::anonymizeTrace($trace) : null,
        );
    }

    public static function logException(\Exception $e)
    {
        $exceptionClass = get_class($e);
        $exceptionCode = $e->getCode();

        $message = Profiler\SqlAnonymizer::anonymize($e->getMessage());

        self::$error = array(
            "message" => $message,
            "file" => $e->getFile(),
            "line" => $e->getLine(),
            "type" => $exceptionClass . ($exceptionCode != 0 ? sprintf('(%s)', $exceptionCode) : ''),
            "trace" => self::anonymizeTrace($e->getTrace()),
        );
    }

    private static function anonymizeTrace($trace)
    {
        if (is_string($trace)) {
            return $trace;
        }

        foreach ($trace as $traceLineId => $traceLine) {
            if (isset($traceLine['args'])) {

                foreach ($traceLine['args'] as $argId => $arg) {
                    if (is_object($arg)) {
                        $traceLine['args'][$argId] = get_class($arg);
                    } else {
                        $traceLine['args'][$argId] = gettype($arg);
                    }
                }
                $trace[$traceLineId] = $traceLine;

            }
        }
        return $trace;
    }

    public static function shutdown()
    {
        if ((self::$extensionFlags & self::EXT_FATAL) > 0) {
            $callback = self::$extensionPrefix . '_last_fatal_error';
            $lastError = $callback();
        } else {
            $lastError = error_get_last();
        }

        if ($lastError && ($lastError["type"] === E_ERROR || $lastError["type"] === E_PARSE || $lastError["type"] === E_COMPILE_ERROR)) {
            self::logFatal(
                $lastError["message"],
                $lastError["file"],
                $lastError["line"],
                $lastError["type"],
                isset($lastError["trace"]) ? $lastError["trace"] : null
            );
        }

        if (function_exists("http_response_code") && http_response_code() >= 500) {
            self::logFatal("PHP request set error HTTP response code to '" . http_response_code() . "'.", "", 0, E_USER_ERROR);
        }

        self::stop();
    }

    /**
     * Get a unique identifier for the current profile trace.
     *
     * Base64 encoded version of the binary representation of a UUID.
     *
     * @return string
     */
    public static function getProfileTraceUuid()
    {
        if (self::$uid === null) {
            $uuid = base64_encode(
                pack(
                    "h*",
                    sprintf(
                        '%04x%04x%04x%04x%04x%04x%04x%04x',
                        mt_rand(0, 0xffff),
                        mt_rand(0, 0xffff),
                        mt_rand(0, 0xffff),
                        mt_rand(0, 0x0fff) | 0x4000,
                        mt_rand(0, 0x3fff) | 0x8000,
                        mt_rand(0, 0xffff),
                        mt_rand(0, 0xffff),
                        mt_rand(0, 0xffff)
                    )
                )
            );
            $uuid = str_replace("/", "_", $uuid);
            $uuid = str_replace("+", "-", $uuid);

            self::$uid = substr($uuid, 0, strlen($uuid) - 2);
        }

        return self::$uid;
    }

    /**
     * Render HTML that the profiling toolbar picks up to display inline development information.
     *
     * This method does not display the html, it just returns it.
     *
     * @return string
     */
    public static function renderToolbarBootstrapHtml()
    {
        if (self::$started == false || self::$sampling === true) {
            return;
        }

        return sprintf(
            '<div id="QafooLabs-Profiler-Profile-Id" data-trace-id="%s" style="display:none !important;" aria-hidden="true"></div>',
            self::getProfileTraceUuid()
        );
    }
}
