<?php
namespace DebugHelper\Tools;

class Log extends Abstracted
{
    /**
     * Clears the log file
     *
     * @var string $data Data to be saved in the new created log.
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
     *
     * @param mixed $data Data to be written in the log.
     * @param string $header Identifier for the header of the log entry.
     */
    public function log($data, $header = 'LOG', $caller_depth = 1)
    {
        static $last_log = false;
        static $first_log = false;

        // Get filename and last directory.
        $pos = $this->getCallerInfo(false, $caller_depth);
        $path = substr($pos['file'], -32);

        $ms = microtime(true);
        $elapsed = date('Y/m/d H:i:s ') . ($ms - floor($ms));

        if ($last_log) {
            $elapsed = ' +' . number_format($ms - $last_log, 3);
        }
        if ($first_log) {
            $elapsed .= ' +' . number_format($ms - $first_log, 3);
        } else {
            $first_log = $ms;
        }
        $last_log = $ms;
        $ms = explode('.', $ms);
        $ms = isset($ms[1]) ? sprintf('%03d', round($ms[1])) : 0;

        if (is_array($data) || is_object($data)) {
            $data = $this->toArray($data);
            $data = $this->getArrayDump($data);
        } elseif (empty($data)) {
            $data = '';
        }

        // Build label.
        $pos = "$path:{$pos['line']} [$elapsed]";
        if ($header) {
            $log = "\n[$header] {$pos}\n{$data}";
        } elseif ($header === false) {
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
    public function logUnique($data, $extra = '', $caller_depth = 1)
    {
        // Get filename and last directory.
        $pos = $this->getCallerInfo(false, $caller_depth);
        $path = explode('/', $pos['file']);
        $path = array_splice($path, -2);
        $path = implode('/', $path);

        if (is_array($data) || is_object($data)) {
            $data = $this->toArray($data);
            $data = $this->getArrayDump($data);
        } elseif (empty($data)) {
            $data = '';
        }

        // Build label.
        $log = "$path:{$pos['line']}\n\n$data";

        $path = $this->getLogPath(true, $extra);

        error_log($log, 3, $path);
        self::log(basename($path), 'UNIQUE', $caller_depth + 1);
    }

    /**
     * Shows the HTML trace.
     *
     * @param boolean $finish Finish the script execution.
     * @param boolean $return_trace Returns the trace instead of printing it.
     *
     * @return mixed
     */
    public function showtrace()
    {
        $trace = xdebug_get_function_stack();
        $trace = array_slice($trace, 0, count($trace) - 4);

        $debug_backtrace = '';
        foreach ($trace as $item) {

            if (isset($item['function'])) {
                $function = isset($item['class']) ? $item['class'] . '::' . $item['function'] : $item['function'];
            } else {
                $function = 'inlcude: ' . $item['include_filename'];
            }
            $file = $item['file'];

            $debug_backtrace
                .= <<<ROW
{$item['file']}:{$item['line']} {$item['line']} $function()

ROW;
        }
        self::log($debug_backtrace, 'TRACE', 5);
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