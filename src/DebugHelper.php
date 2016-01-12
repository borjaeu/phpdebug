<?php
/**
 * Helper with static methods for debug.
 */
class DebugHelper
{
    /**
     * The debug will output code.
     *
     * @var string
     */
    const DEBUG_OUTPUT = 'debug_output';

    /**
     * Dump will be shown collapsed.
     *
     * @var string
     */
    const DUMP_COLLAPSED = 'dump_collapsed';

    /**
     * All debug operations enabled by default.
     *
     * @var boolean
     */
    protected static $options = array();

    protected static $methods
        = array(
            'logUnique' => 'DebugHelperLog::logUnique',
            'request' => 'DebugHelperRequest::get',
            'getCleanRequestUri' => 'DebugHelperRequest::getCleanRequestUri',
            'logTrace' => 'DebugHelperLog::showtrace',
            'export' => 'DebugHelperTransform::export',
            'mark' => 'DebugHelperMark::mark',
            'save' => 'DebugHelperData::save',
            'get' => 'DebugHelperData::get',
            'getAll' => 'DebugHelperData::getAll'
        );

    protected static $objects = array();

    /**
     * Loads the class Object.
     *
     * @param string $class_name Name of the class to load.
     *
     * @return Object
     */
    public static function getClass($class_name)
    {
        if (empty(self::$objects[$class_name])) {
            self::$objects[$class_name] = new $class_name();
        }
        return self::$objects[$class_name];
    }

    /**
     * @param string $action action to load.
     * @param array $parameters Parameters for the action.
     *
     * @return mixed
     */
    protected static function call($action, $parameters)
    {
        list($class, $method) = explode('::', $action);
        return call_user_func_array(array(self::getClass($class), $method), $parameters);
    }

    /**
     * Clears the log file
     *
     * @var string $data Data to be saved in the new created log.
     */
    public static function clearLog($data = null)
    {
        return self::getClass('\DebugHelper\Tools\Log')->clearLog($data);
    }

    /**
     * Save the data to a log file.
     *
     * @param mixed $data Data to be written in the log.
     * @param string $header Identifier for the header of the log entry.
     */
    public static function logUnique($data, $extra = '', $caller_depth = 1)
    {
        return self::getClass('\DebugHelper\Tools\Log')->logUnique($data, $extra, $caller_depth);
    }

    public static function profile()
    {
        return self::getClass('\DebugHelper\Tools\Profile')->profile();
    }

    public static function profileReport($file = false)
    {
        return self::getClass('\DebugHelper\Tools\Profile')->profileReport($file);
    }

    /**
     * Compares to arrays and give visual feedback of the direfferecnes.
     *
     * @param array $array_a First array to compare.
     * @param array $array_b Second array to compare.
     * @param boolean $just_changes Don't show rows that are the same.
     */
    public static function compare($before, $after, $just_changes = false)
    {
        return self::getClass('\DebugHelper\Tools\Arrays')->compare($before, $after, $just_changes);
    }

    /**
     * Look for information inside an array.
     *
     * @param array $data First array to compare.
     * @param string $needle Data to search in the array.
     */
    public static function search($data, $needle)
    {
        return self::getClass('\DebugHelper\Tools\Arrays')->search($data, $needle);
    }

    /**
     * Proxy method, executes the called attributes in the class.
     *
     * @param string $method Method called
     * @param array $parameters Parameters sent to the method.
     *
     * @return mixed
     * @throws Exception When the method is not valid
     */
    public static function __callStatic($method, $parameters)
    {
        if (!self::isEnabled(self::DEBUG_OUTPUT)) {
            return;
        }
        if (isset(self::$methods[$method])) {
            return self::call(self::$methods[$method], $parameters);
        }
        self::dump($method);
        self::showtrace(false);
        throw new Exception("Invalid Debug Helper method '$method'");
    }

    /**
     * Checks if some option is enabled or not.
     */
    public static function isEnabled($option)
    {
        return isset(self::$options[$option]) ? self::$options[$option] : true;
    }

    /**
     * Enables one option.
     */
    public static function enable($option)
    {
        self::$options[$option] = true;
    }

    /**
     * Disables one option.
     */
    public static function disable($option)
    {
        self::$options[$option] = false;
    }

    public static function getDebugDir()
    {
        $debug_dir = isset(self::$options['phpdebug_dir'])
            ? self::$options['phpdebug_dir']
            : __DIR__ . '/../temp/';
        if (!is_dir($debug_dir)) {
            mkdir($debug_dir);
        }
        return $debug_dir;
    }

    public static function setDebugDir($path)
    {
        self::$options['phpdebug_dir'] = $path;
    }

    /**
     * @return bool
     */
    public static function isCli()
    {
        return PHP_SAPI == 'cli';
    }

    public static function init()
    {

    }
}

/**
 * @return \DebugHelper\Tools\Watcher
 */
function k_get_watcher()
{
    return DebugHelper::getClass('\DebugHelper\Tools\Watcher');
}

function k_collect_errors()
{
    set_error_handler(array('DebugHelper\Error', 'handler'));
}

/**
 * Shows the HTML trace.
 *
 * @param boolean $finish Finish the script execution.
 * @param boolean $returnTrace Returns the trace instead of printing it.
 * @return mixed
 */
function k_trace($returnTrace = false)
{
    return DebugHelper::getClass('\DebugHelper\Tools\Dump')->showtrace($returnTrace);
}

/**
 * Save the data to a log file.
 *
 * @param mixed $data Data to be written in the log.
 * @param string $header Identifier for the header of the log entry.
 */
function k_log($data, $header = 'LOG', $caller_depth = 2)
{
    return DebugHelper::getClass('\DebugHelper\Tools\Log')->log($data, $header, $caller_depth);
}


/**
 * Displays the data passed as information.
 *
 * @param mixed $data Information to be dumped to the browser.
 */
function k_dump($data = '')
{
    return DebugHelper::getClass('\DebugHelper\Tools\Dump')->dump($data);
}

/**
 * Displays the data passed as information.
 */
function k_die()
{
    DebugHelper::getClass('\DebugHelper\Tools\Dump')->dump();
    exit;
}

/**
 * Begins the trace to watch where the code goes.
 *
 * @param Exception $exception
 */
function k_exception($exception)
{
    return DebugHelper::getClass('\DebugHelper\Tools\Exception')->exception($exception);
}