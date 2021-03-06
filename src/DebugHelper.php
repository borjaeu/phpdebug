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
     * Has been intialized?
     *
     * @var boolean
     */
    protected static $initialized = false;

    /**
     * Initializes the debug
     *
     * @param array $options
     */
    public static function init(array $options = [])
    {
        $defaultOptions = [
            'debug_output'   => true,
            'dump_collapsed' => true,
            'debug_dir'      =>  __DIR__.'/../temp/',
            'handler_url'    => 'codebrowser://<file>[:<line>]',
            'handler_source' => '',
            'handler_target' => '',

        ];
        self::$initialized = true;

        self::$options = array_merge($defaultOptions, $options);
        self::checkOptions();
    }

    /**
     * @param string $option
     * @return mixed
     */
    public static function get($option)
    {
        if (!self::$initialized) {
            self::init();
        }

        return isset(self::$options[$option]) ? self::$options[$option] : null;
    }

    /**
     * Set the an option to the given value
     *
     * @param string $option
     * @param mixed  $value
     */
    public static function set($option, $value)
    {
        if (!self::$initialized) {
            self::init();
        }

        self::$options[$option] = $value;
        self::checkOptions();
    }

    /**
     * @return bool
     */
    public static function isCli()
    {
        return PHP_SAPI == 'cli';
    }

    /**
     * @return \DebugHelper\Tools\Watcher
     */
    public static function watcher()
    {
        static $watcher;

        if (empty($watcher)) {
            $watcher = new \DebugHelper\Tools\Watcher();
        }

        return $watcher;
    }

    /**
     * Shows the HTML trace
     */
    public static function trace()
    {
        self::dump()->showtrace();
    }

    /**
     * Save the data to a log file.
     *
     * @return \DebugHelper\Tools\Log
     */
    public static function log()
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
     * Save the data to a log file.
     */
    public static function timer($group, $message = '')
    {
        static $timer;

        if (empty($timer)) {
            $timer = new \DebugHelper\Tools\Timer();
        }
        $timer->delta($group, $message);
    }

    /**
     * Displays the data passed as information.
     *
     * @return \DebugHelper\Tools\Dump
     */
    public static function dump()
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
    public static function die()
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
    public static function exception($exception)
    {
        static $exceptionDebug;

        if (empty($exceptionDebug)) {
            $exceptionDebug = new \DebugHelper\Tools\Exception();
        }
        $exceptionDebug->exception($exception);
    }

    /**
     * Begins the trace to watch where the code goes.
     *
     * @param mixed $data
     * @param array $keys
     * @return array
     */
    public static function simplify($data, $keys)
    {
        static $arrayHelper;

        if (empty($arrayHelper)) {
            $arrayHelper = new \DebugHelper\Tools\Arrays();
        }

        return $arrayHelper->simplify($data, $keys);
    }

    /**
     * Checks for the valid options
     */
    private static function checkOptions()
    {
        if (!is_dir(self::$options['debug_dir'])) {
            mkdir(self::$options['debug_dir']);
        }
    }
}
