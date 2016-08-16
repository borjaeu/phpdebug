<?php
namespace DebugHelper\Tools;

/**
 * Class Log
 * @package DebugHelper\Tools
 */
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
     */
    public function logTrace()
    {
        $output = new Output(Output::MODE_FILE, $this->getLogPath());
        $output->open();
        \DebugHelper::dump()->showtrace($output);
        $output->close();
    }

    /**
     * @param bool   $unique
     * @param string $extra
     * @return string
     */
    protected function getLogPath($unique = false, $extra = '')
    {
        $logPath = \DebugHelper::get('debug_dir');
        if ($unique) {
            $logPath .= date('Y_m_d_h_i_s').preg_replace('/\d+\./', '', microtime(true)).'_'.$extra.'.txt';
        } else {
            $logPath .= 'log.txt';
        }

        return $logPath;
    }
}
