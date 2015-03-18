<?php
set_error_handler( array( 'DebugHelper\Error', 'handler' ) );
ini_set( 'xdebug.collect_params', 1 ); // 0 None, 1, Simple, 3 Full

/**
 * Helper with static methods for debug.
 */
Class DebugHelper
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
            'request'            => 'DebugHelperRequest::get',
            'getCleanRequestUri' => 'DebugHelperRequest::getCleanRequestUri',
            'logTrace'           => 'DebugHelperLog::showtrace',
            'export'             => 'DebugHelperTransform::export',
            'mark'               => 'DebugHelperMark::mark',
            'save'               => 'DebugHelperData::save',
            'get'                => 'DebugHelperData::get',
            'getAll'             => 'DebugHelperData::getAll'
        );

    protected static $objects = array();

    /**
     * Loads the class Object.
     *
     * @param string $class_name Name of the class to load.
     *
     * @return Object
     */
    protected static function getClass( $class_name )
    {
        if (empty( self::$objects[$class_name] )) {
            self::$objects[$class_name] = new $class_name();
        }
        return self::$objects[$class_name];
    }

    /**
     * @param string $action     action to load.
     * @param array  $parameters Parameters for the action.
     *
     * @return mixed
     */
    static protected function call( $action, $parameters )
    {
        list( $class, $method ) = explode( '::', $action );
        return call_user_func_array( array( self::getClass( $class ), $method ), $parameters );
    }

    /**
     * Displays the data passed as information.
     *
     * @param mixed $data Information to be dumped to the browser.
     */
    static public function dump($data = '', $offset = 0, $raw = false)
    {
        return self::getClass( '\DebugHelper\Tools\Dump' )->dump($data, $offset, $raw);
    }

    /**
     * Begins the trace to watch where the code goes.
     *
     * @param boolean $silent
     * @param boolean $trace_file Name of the file to save the data.
     * @param boolean $ignore     Name of the file to save the data.
     */
    static public function watch( $silent = false, $trace_file = false, $ignore = false )
    {
        return self::getClass( '\DebugHelper\Tools\Watcher' )->watch( $silent, $trace_file, $ignore );
    }

    /**
     * Shows a coverage report of the trace since the watch() method was called.
     *
     * @param boolean $finish_execution Ends the script execution.
     *
     * @return string
     */
    static public function endWatch( $finish_execution = false )
    {
        return self::getClass( '\DebugHelper\Tools\Watcher' )->endWatch( $finish_execution );
    }

    /**
     * Shows the HTML trace.
     *
     * @param boolean $finish       Finish the script execution.
     * @param boolean $return_trace Returns the trace instead of printing it.
     *
     * @return mixed
     */
    static public function showtrace( $finish = true, $return_trace = false )
    {
        return self::getClass( '\DebugHelper\Tools\Dump' )->showtrace( $finish, $return_trace );
    }

    /**
     * Clears the log file
     *
     * @var string $data Data to be saved in the new created log.
     */
    static public function clearLog( $data = null )
    {
        return self::getClass( '\DebugHelper\Tools\Log' )->clearLog( $data );
    }

    /**
     * Save the data to a log file.
     *
     * @param mixed  $data   Data to be written in the log.
     * @param string $header Identifier for the header of the log entry.
     */
    static public function log( $data, $header = 'LOG', $caller_depth = 2 )
    {
        return self::getClass( '\DebugHelper\Tools\Log' )->log( $data, $header, $caller_depth );
    }

    /**
     * Save the data to a log file.
     *
     * @param mixed  $data   Data to be written in the log.
     * @param string $header Identifier for the header of the log entry.
     */
    static public function logUnique( $data, $extra = '', $caller_depth = 1 )
    {
        return self::getClass( '\DebugHelper\Tools\Log' )->logUnique( $data, $extra, $caller_depth );
    }

    static public function profile()
    {
        return self::getClass( '\DebugHelper\Tools\Profile' )->profile();
    }

    static public function profileReport()
    {
        return self::getClass( '\DebugHelper\Tools\Profile' )->profileReport();
    }

    /**
     * Compares to arrays and give visual feedback of the direfferecnes.
     *
     * @param array   $array_a      First array to compare.
     * @param array   $array_b      Second array to compare.
     * @param boolean $just_changes Don't show rows that are the same.
     */
    static public function compare( $before, $after, $just_changes = false )
    {
        return self::getClass( '\DebugHelper\Tools\Arrays' )->compare( $before, $after, $just_changes );
    }

    /**
     * Look for information inside an array.
     *
     * @param array  $data   First array to compare.
     * @param string $needle Data to search in the array.
     */
    static public function search( $data, $needle )
    {
        return self::getClass( '\DebugHelper\Tools\Arrays' )->search( $data, $needle );
    }

    /**
     * Begins the trace to watch where the code goes.
     *
     * @param Exception $exception
     */
    static public function exception( $exception )
    {
        return self::getClass( '\DebugHelper\Tools\Exception' )->exception( $exception );
    }

    /**
     * Proxy method, executes the called attributes in the class.
     *
     * @param string $method     Method called
     * @param array  $parameters Parameters sent to the method.
     *
     * @return mixed
     * @throws Exception When the method is not valid
     */
    static public function __callStatic( $method, $parameters )
    {
        if (!self::isEnabled( self::DEBUG_OUTPUT )) {
            return;
        }
        if (isset( self::$methods[$method] )) {
            return self::call( self::$methods[$method], $parameters );
        }
        self::dump( $method );
        self::showtrace( false );
        throw new Exception( "Invalid Debug Helper method '$method'" );
    }

    /**
     * Checks if some option is enabled or not.
     */
    static public function isEnabled( $option )
    {
        return isset( self::$options[$option] ) ? self::$options[$option] : true;
    }

    /**
     * Enables one option.
     */
    static public function enable( $option )
    {
        self::$options[$option] = true;
    }

    /**
     * Disables one option.
     */
    static public function disable( $option )
    {
        self::$options[$option] = false;
    }

    static public function getDebugDir()
    {
        $debug_dir = isset( self::$options['phpdebug_dir'] ) ? self::$options['phpdebug_dir'] : sys_get_temp_dir(). '/phpdebug/';
        if ( !is_dir( $debug_dir ) ) {
            mkdir( $debug_dir );
        }
        return $debug_dir;
    }

    static public function setDebugDir( $path )
    {
        self::$options['phpdebug_dir'] = $path;
    }
}

?>