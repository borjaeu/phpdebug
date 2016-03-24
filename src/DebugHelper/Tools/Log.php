<?php
namespace DebugHelper\Tools;

use DebugHelper\Tools\Helper\Trace;

class Log extends Abstracted
{
    /**
     * Log header
     *
     * @var string
     */
    protected $header = 'LOG';
    /**
     * @var bool
     */
    protected $lastLog = false;
    /**
     * @var bool
     */
    protected $firstLog = false;

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
        $pos = $this->getCallerInfo();
        $path = substr($pos->getFile(), -32);

        $ms = microtime(true);
        $elapsed = date('Y/m/d H:i:s');

        if ($this->lastLog) {
            $elapsed = ' +' . number_format($ms - $this->lastLog, 3);
        }
        if ($this->firstLog) {
            $elapsed .= ' +' . number_format($ms - $this->firstLog, 3);
        } else {
            $this->firstLog = $ms;
        }
        $this->lastLog = $ms;

        $data = '';
        foreach(func_get_args() as $argument) {
            if (is_array($argument) || is_object($argument)) {
                $data .= $this->getArrayDump($this->toArray($argument));
            } else {
                $data .= var_export($argument, true) . PHP_EOL;
            }
        }
        $data = trim($data);

        $line = $pos->getLine();
        $source = $pos->getSource();
        $pos = "$path:$line [$elapsed]";
        if ($this->header) {
            $log = "\n[{$this->header}] {$pos} '$source'\n{$data}";
        } elseif ($this->header === false) {
            $log = "\n$data";
        } else {
            $log = "\n{$pos} {$data}";
        }
        $path = $this->getLogPath();
        error_log($log, 3, $path);
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

    /**
     * Convert object to array.
     *
     * @param object $data Data to convert to array
     *
     * @return array
     */
    protected function toArray($data)
    {
        $result = array();
        if (is_object($data)) {
            $data = get_object_vars($data);

        }
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $result[$key] = $this->toArray($value);
            }
        } else {
            $result = $data;
        }
        return $result;
    }

    /**
     * Displays the array passed as information as a string.
     *
     * @param array $array Information to be dumped to the browser.
     *
     * @return string
     */
    protected function getArrayDump($array)
    {
        $data = print_r($array, true);
        $data = preg_replace(
            array(
                "/\n\n/",
                '/Array\s*\n\s*\(/',
                '/\[(.*)\] =>(.*)\n/',
                "/'array\(',/"
            ),
            array(
                "\n",
                'array(',
                "$1:$2\n",
                'array('
            ),
            $data
        );
        return $data;
    }
}