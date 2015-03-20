<?php
namespace DebugHelper\Tools;

class Watcher extends Abstracted
{
    /**
     * File to save the trace information.
     *
     * @var string
     */
    static protected $trace_file;

    /**
     * Begins the trace to watch where the code goes.
     *
     * @param boolean $silent
     * @param boolean $trace_file Name of the file to save the data.
     * @param boolean $ignore     Name of the file to save the data.
     */
    public function watch( $silent = false, $trace_file = false, $ignore = false )
    {
        static $watching = false;

        if ($watching) {
            if ($ignore) {
                return;
            }
            $pos = $this->getCallerHtml( 1, false );
            echo <<<ERROR
<pre>
Watch already started in $watching
Could not be start at $pos
</pre>
ERROR;
            exit;
        } else {
            $watching = self::getCallerHtml( 1, false );
        }

        ini_set( 'xdebug.collect_params', 3 ); // 0 None, 1, Simple, 3 Full
        ini_set( 'xdebug.collect_return', 0 ); // 0 None, 1, Yes
        ini_set( 'xdebug.var_display_max_depth', 2 );
        ini_set( 'xdebug.var_display_max_data', 128 );
        $trace_key = $trace_file ? $trace_file : date( 'Y_m_d_h_i_s' );

        self::$trace_file =  \DebugHelper::getDebugDir(). $trace_key;
        if ($trace_file && is_file(self::$trace_file )) {
            return;
        }

        file_put_contents(self::$trace_file . '.svr', print_r( $_SERVER, true ) );

        // Info about the data.
        $log_info = $this->getCallerInfo( false, 4 );
        $file = strlen( $log_info['file'] ) > 36 ? '...' . substr( $log_info['file'], -35 ) : $log_info['file'];
        \DebugHelper::log( "Watch started at $file:{$log_info['line']}", 'AUTO' );

        if (!$silent) {
            $pos = $this->getCallerHtml( 4, false );
            echo <<<OUTPUT
Watch started at $pos
<a href="http://base.bmorales.coredev/utils/phpdebug/index.php">Debugs</a>

OUTPUT;
        }
        xdebug_start_trace(self::$trace_file);
        xdebug_start_code_coverage();
        register_shutdown_function( '\DebugHelper\Tools\Watcher::shutDownEndWatch' );
    }

    /**
     * Shows a coverage report of the trace since the watch() method was called.
     *
     * @param boolean $finish_execution Ends the script execution.
     *
     * @return string
     */
    public function endWatch( $finish_execution = false )
    {
        if (empty(self::$trace_file )) {
            return;
        }
        xdebug_stop_trace();

        $coverage = $this->getCodeCoverage(self::$trace_file . '.raw' );

        file_put_contents(self::$trace_file . '.cvg', serialize( $coverage ) );
        self::$trace_file = '';
        if ($finish_execution) {
            die( sprintf( "<pre><a href=\"codebrowser:%s:%d\">DIE</a></pre>", __FILE__, __LINE__ ) );
        }
    }

    /**
     * Shows a coverage report of the trace since the watch() method was called.
     */
    static public function shutDownEndWatch()
    {
        \DebugHelper::endWatch();
    }

    /**
     * Gets code coverage from the xdebug.
     *
     * @param boolean $rawfile If set, determines a file to copy the raw contents for the coverage.
     */
    protected function getCodeCoverage( $rawfile = false )
    {
        $code_coverage = xdebug_get_code_coverage();

        if (false !== $rawfile) {
            file_put_contents( $rawfile, print_r( $code_coverage, true ) );
        }

        $result = array();
        foreach ($code_coverage as $file => $lines) {
            if ($file === __FILE__) {
                continue;
            }
            if (isset( $file ) && is_file( $file )) {
                // Get code line.
                $fp = fopen( $file, 'r' );
                $i = 0;
                $class = '';
                $last_function = '';
                $function = '';
                $valid_function = false;
                while (!feof( $fp )) {
                    $i++;
                    $line = fgets( $fp );
                    if (preg_match( '/^\s*(abstract)?\s*[Cc]lass\s+([^\s]*)/', $line, $matches )) {
                        // Get current class.
                        $class = $matches[2];
                    } else if (preg_match( '/^\s*(.*)function\s+([^\(]*)\((.*)\)/', $line, $matches )) {
                        // Save previous method if valid.
                        if ($valid_function) {
                            $result[$function] = $data;
                        }

                        // Get current method.
                        $function = "{$class}::{$matches[2]}";
                        $valid_function = false;
                        $data = array(
                            'file'  => $file,
                            'lines' => array()
                        );
                    }

                    $data['lines'][$i] = array(
                        'code'    => $line,
                        'covered' => isset( $lines[$i] )
                    );
                    if (isset( $lines[$i] )) {
                        $valid_function = true;
                    }
                }
                // Save previous method if valid.
                if ($valid_function) {
                    $result[$function] = $data;
                }
            }
        }
        return $result;
    }


}