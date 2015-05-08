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
     * @param boolean $ignore Name of the file to save the data.
     */
    public function watch($silent = false, $trace_file = false, $ignore = false)
    {
        static $watching = false;

        if ($watching) {
            if ($ignore) {
                return;
            }
            $pos = $this->getCallerDetails(1, false);
            echo <<<ERROR
<pre>
Watch already started in $watching
Could not be start at $pos
</pre>
ERROR;
            exit;
        } else {
            $watching = self::getCallerDetails(1, false);
        }

        ini_set('xdebug.profiler_enable', 1);
        ini_set('xdebug.profiler_output_dir', \DebugHelper::getDebugDir());
        ini_set('xdebug.collect_params', 3); // 0 None, 1, Simple, 3 Full
        ini_set('xdebug.collect_return', 0); // 0 None, 1, Yes
        ini_set('xdebug.var_display_max_depth', 2);
        ini_set('xdebug.var_display_max_data', 128);
        preg_match('/0\.(?P<decimal>\d+)/', microtime(), $matches);
        $trace_key = $trace_file ? $trace_file : date('Y_m_d_h_i_s_') . $matches['decimal'];

        self::$trace_file = \DebugHelper::getDebugDir() . $trace_key;
        if ($trace_file && is_file(self::$trace_file)) {
            return;
        }

        file_put_contents(self::$trace_file . '.svr', json_encode(array(
            'server' => $_SERVER,
            'post' => $_POST,
            'get' => $_GET,
            'files' => $_FILES
        ), JSON_PRETTY_PRINT));

        // Info about the data.
        $log_info = $this->getCallerInfo(false, 2);
        $file = strlen($log_info['file']) > 36 ? '...' . substr($log_info['file'], -35) : $log_info['file'];
        \DebugHelper::log("Watch started at $file:{$log_info['line']}", 'AUTO');

        if (!$silent) {
            $pos = $this->getCallerDetails(4, false);
            echo <<<OUTPUT
Watch started at $pos
<a href="http://base.bmorales.coredev/utils/phpdebug/index.php">Debugs</a>

OUTPUT;
        }
        xdebug_start_trace(self::$trace_file);
        xdebug_start_code_coverage();
        register_shutdown_function('\DebugHelper\Tools\Watcher::shutDownEndWatch');
    }

    /**
     * Shows a coverage report of the trace since the watch() method was called.
     *
     * @param boolean $finish_execution Ends the script execution.
     *
     * @return string
     */
    public function endWatch($finish_execution = false)
    {
        if (empty(self::$trace_file)) {
            return;
        }
        xdebug_stop_trace();

        $coverage = $this->getCodeCoverage();

        file_put_contents(self::$trace_file . '.cvg', json_encode($coverage));
        self::$trace_file = '';
        if ($finish_execution) {
            die(sprintf("<pre><a href=\"codebrowser:%s:%d\">DIE</a></pre>", __FILE__, __LINE__));
        }
    }

    /**
     * Shows a coverage report of the trace since the watch() method was called.
     */
    public static function shutDownEndWatch()
    {
        \DebugHelper::endWatch();
    }

    /**
     * Gets code coverage from the xdebug.
     */
    protected function getCodeCoverage()
    {
        $code_coverage = xdebug_get_code_coverage();
        $result = array();
        foreach ($code_coverage as $file => $lines) {
            $result[$file] = $lines;
        }
        return $result;
    }
}
