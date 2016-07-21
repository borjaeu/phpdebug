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
    const OPTION_DUMP_COLLAPSED = 'dump_collapsed';

    /**
     * All debug operations enabled by default.
     *
     * @var boolean
     */
    protected static $options = array();

    /**
     * Checks if some option is enabled or not.
     *
     * @param string $option
     * @return bool
     */
    public static function isEnabled($option)
    {
        return isset(self::$options[$option]) ? self::$options[$option] : true;
    }

    /**
     * Enables one option.
     *
     * @param string $option
     */
    public static function enable($option)
    {
        self::$options[$option] = true;
    }

    /**
     * Disables one option.
     *
     * @param string $option
     */
    public static function disable($option)
    {
        self::$options[$option] = false;
    }

    /**
     * @return string
     */
    public static function getDebugDir()
    {
        $debugDir = isset(self::$options['phpdebug_dir'])
            ? self::$options['phpdebug_dir']
            : __DIR__ . '/../temp/';
        if (!is_dir($debugDir)) {
            mkdir($debugDir);
        }
        return $debugDir;
    }

    public static function setDebugDir($path)
    {
        self::$options['phpdebug_dir'] = $path;
    }

    /**
     * @return string
     */
    public static function getHandler()
    {
        $handler = isset(self::$options['url_handler'])
            ? self::$options['url_handler']
            : [
                'handler' => 'codebrowser:<file>[:<line>]',
                'source' => '',
                'target' => ''
            ];

        return $handler;
    }

    public static function setHandler($handler, $sourcePath = '', $realPath = '')
    {
        self::$options['url_handler'] = [
            'handler' => $handler,
            'source' => $sourcePath,
            'target' => $realPath
        ];
    }

    /**
     * @return bool
     */
    public static function isCli()
    {
        return PHP_SAPI == 'cli';
    }

    /**
     * Initializes the debug
     */
    public static function init()
    {
    }
}

/**
 * @return \DebugHelper\Tools\Watcher
 */
function k_watcher()
{
    static $watcher;

    if (empty($watcher)) {
        $watcher = new \DebugHelper\Tools\Watcher();
    }

    return $watcher;
}

function k_collect_errors()
{
    set_error_handler(array('DebugHelper\Error', 'handler'));
}

/**
 * Shows the HTML trace
 */
function k_trace()
{
    k_dump()->showtrace();
}

/**
 * Shows the HTML trace
 */
function k_log_trace()
{
    k_log()->logTrace();
}

/**
 * Save the data to a log file.
 *
 * @return \DebugHelper\Tools\Log
 */
function k_log()
{
    static $log;

    if (empty($log)) {
        $log = new \DebugHelper\Tools\Log();
    }
    $args = func_get_args();
    if (!empty($args)) {
        call_user_func_array([$log, 'log'], $args);
    }
    return $log;
}

/**
 * Displays the data passed as information.
 *
 * @return \DebugHelper\Tools\Dump
 */
function k_dump()
{
    static $dump;

    if (empty($dump)) {
        $dump = new \DebugHelper\Tools\Dump();
    }
    $args = func_get_args();
    if (!empty($args)) {
        call_user_func_array([$dump, 'dump'], $args);
    }
    return $dump;
}

/**
 * Displays the data passed as information.
 */
function k_die()
{
    $dump = new \DebugHelper\Tools\Dump();
    $dump->dump();
    exit;
}

/**
 * Begins the trace to watch where the code goes.
 *
 * @param Exception $exception
 */
function k_exception($exception)
{
    static $exceptionDebug;
    if (empty($exceptionDebug)) {
        $exceptionDebug = new \DebugHelper\Tools\Exception();
    }
    $exceptionDebug->exception($exception);
}

/**
 * Enables an option
 *
 * @param string $option Name of the option to enable
 */
function enable($option)
{
    \DebugHelper::enable($option);
}

/**
 * Disables an option
 *
 * @param string $option Name of the option to disable
 */
function k_disable($option)
{
    \DebugHelper::disable($option);
}
