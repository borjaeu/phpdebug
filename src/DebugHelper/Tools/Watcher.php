<?php
namespace DebugHelper\Tools;

class Watcher extends Abstracted
{
    /**
     * File to save the trace information.
     *
     * @var string
     */
    protected $traceFile;

    /**
     * Flag to determine when the watch is already active
     *
     * @var string
     */
    protected $watching;

    /**
     * Verbosity level
     *
     * @var bool
     */
    protected $level;

    /**
     * Shall execute trace
     *
     * @var bool
     */
    protected $trace;

    /**
     * Shall execute coverage
     *
     * @var bool
     */
    protected $coverage;

    /**
     * Watcher constructor.
     *
     * Initializes the logging
     */
    public function __construct()
    {
        $this->level = 100;
        $this->watching = false;
        $this->trace = true;
        $this->coverage = true;
        $this->watching = false;

        preg_match('/0\.(?P<decimal>\d+)/', microtime(), $matches);
        $traceFile = date('Y_m_d_h_i_s_') . $matches['decimal'];

        $this->setTraceFile($traceFile);

        file_put_contents($this->traceFile . '.svr', json_encode(array(
            'server' => $_SERVER,
            'post' => $_POST,
            'get' => $_GET,
            'files' => $_FILES
        ), JSON_PRETTY_PRINT));
    }

    /**
     * @param string $file Filename of the trace
     * @return $this
     */
    public function setTraceFile($file)
    {
        $this->traceFile = \DebugHelper::getDebugDir() . $file;
        return $this;
    }

    /**
     * Change the level of verbosity of the app
     *
     * @param integer $level of the output to show
     * @return $this
     */
    public function setLevel($level)
    {
        $this->level = $level;
        return $this;
    }

    /**
     * Disable the coverage info
     *
     * @return $this
     */
    public function disableCoverage()
    {
        $this->coverage = false;
        return $this;
    }

    /**
     * Disable the trace info
     *
     * @return $this
     */
    public function disableTrace()
    {
        $this->trace = false;
        return $this;
    }

    /**
     * Begins the trace to watch where the code goes.
     */
    public function watch()
    {
        if ($this->watching) {
            $pos = $this->getCallerDetails(1, false);
            $error = <<<ERROR
<pre>
Watch already started in {$this->watching}
Could not be start at $pos
</pre>
ERROR;
            $this->output($error, 200);
            exit;
        }

        $this->watching = self::getCallerDetails(1, false);

        if (is_file($this->traceFile)) {
            return;
        }

        $log_info = $this->getCallerInfo(false, 2);
        $file = strlen($log_info['file']) > 36 ? '...' . substr($log_info['file'], -35) : $log_info['file'];
        k_log("Watch started at $file:{$log_info['line']}", 'AUTO');

        $info = <<<OUTPUT
Watch started at {$this->watching}

OUTPUT;
        $this->output($info, 100);

        if ($this->trace) {
            $this->startTrace();
        }

        if ($this->coverage) {
            xdebug_start_code_coverage();
        }
        register_shutdown_function('\DebugHelper\Tools\Watcher::shutDownEndWatch');
    }

    /**
     * Shows a coverage report of the trace since the watch() method was called.
     *
     * @param boolean $finishExecution Ends the script execution.
     * @return string
     */
    public function endWatch($finishExecution = false)
    {
        if (empty($this->traceFile)) {
            return;
        }
        if ($this->trace) {
            xdebug_stop_trace();
        }
        if ($this->coverage) {
            $coverage = $this->getCodeCoverage();
            file_put_contents($this->traceFile . '.cvg', json_encode($coverage));
        }

        $this->traceFile = '';
        if ($finishExecution) {
            die(sprintf("<pre><a href=\"codebrowser:%s:%d\">DIE</a></pre>", __FILE__, __LINE__));
        }
    }

    /**
     * Shows a coverage report of the trace since the watch() method was called.
     */
    public static function shutDownEndWatch()
    {
        $watcher = \DebugHelper::getClass('\DebugHelper\Tools\Watcher');
        $watcher->endWatch();
    }

    protected function startTrace()
    {
        ini_set('xdebug.profiler_enable', 1);
        ini_set('xdebug.profiler_output_dir', \DebugHelper::getDebugDir());
        ini_set('xdebug.collect_params', 0); // 0 None, 1, Simple, 3 Full
        ini_set('xdebug.collect_return', 0); // 0 None, 1, Yes
        ini_set('xdebug.var_display_max_depth', 2);
        ini_set('xdebug.var_display_max_data', 128);
        xdebug_start_trace();
    }

    /**
     * Displays a message depending on the severity level
     *
     * @param string $message message to output
     * @param int $level Level of the message
     */
    protected function output($message, $level)
    {
        if ($this->level >= $level) {
            echo $message;
        }
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
