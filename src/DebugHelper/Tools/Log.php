<?php
namespace DebugHelper\Tools;

use DebugHelper\Tools\Helper\Trace;

class Log
{
    /**
     * Log header
     *
     * @var string
     */
    protected $header = 'LOG';

    /**
     * @param string $header
     * @return Log
     */
    public function setHeader($header)
    {
        $this->header = $header;
        return $this;
    }

    /**
     * Clears the log file
     *
     * @param string $data Data to be saved in the new created log.
     */
    public function clearLog($data = null)
    {
        $path = $this->getLogPath();
        if (is_file($path)) {
            unlink($path);
            touch($path);
        }
        if (!empty($data)) {
            self::log($data);
        }
    }

    /**
     * Save the data to a log file.
     */
    public function log()
    {
        $output = new Output(Output::MODE_FILE, $this->getLogPath());
        $output->open();
        foreach (func_get_args() as $argument) {
            $output->dump($argument);
        }
        $output->close();
    }

    /**
     * Save the data to a log file.
     *
     * @param mixed $data Data to be written in the log.
     * @param string $header Identifier for the header of the log entry.
     */
    public function logUnique($data, $extra = '')
    {
        $pos = $this->getCallerInfo();
        $path = explode('/', $pos['file']);
        $path = array_splice($path, -2);
        $path = implode('/', $path);

        if (is_array($data) || is_object($data)) {
            $data = $this->toArray($data);
            $data = $this->getArrayDump($data);
        } elseif (empty($data)) {
            $data = '';
        }

        $log = "$path:{$pos['line']}\n\n$data";

        $path = $this->getLogPath(true, $extra);

        error_log($log, 3, $path);
        self::log(basename($path), 'UNIQUE');
    }

    /**
     * Shows the text trace
     *
     * @return mixed
     */
    public function showtrace()
    {
        $traceHelper = new Trace();
        $trace = $traceHelper->getTrace();



        $debugBacktrace = '';
        foreach ($trace as $item) {


            $debugBacktrace
                .= <<<ROW
{$item['file']}:{$item['line']} {$item['line']} {$item['call']}()

ROW;
        }
        self::log($debugBacktrace, 'TRACE', 5);
    }

    /**
     * @return string
     */
    protected function getLogPath($unique = false, $extra = '')
    {
        if ($unique) {
            return
                \DebugHelper::getDebugDir() . date('Y_m_d_h_i_s') . preg_replace('/\d+\./', '', microtime(true))
                . '_' . $extra . '.txt';
        }
        return \DebugHelper::getDebugDir() . 'log.txt';
    }
}
