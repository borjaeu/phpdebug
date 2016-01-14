<?php
namespace DebugHelper\Tools;

use DebugHelper\Styles;

class Dump extends Abstracted
{
    /**
     * Displays the data passed as information.
     *
     * @param mixed $data Information to be dumped to the browser.
     */
    public function dump($data = null, $depth = 2)
    {
        static $start = false;

        if ($start === false) {
            $start = microtime(true);
            $split = 0;
        } else {
            $split = microtime(true) - $start;
        }
        $split = number_format($split, 6);

        Styles::showHeader('dump', 'objectToHtml');

        $pos = $this->getCallerInfo($depth);

        \DebugHelper::getClass('\DebugHelper\Tools\Output')->dump($pos, $data);
    }

    /**
     * Shows the HTML trace.
     *
     * @return mixed
     */
    public function showtrace()
    {
        $trace = xdebug_get_function_stack();
        $trace = array_slice($trace, 0, count($trace) - 1);
        $debugTrace = self::getDebugTrace($trace);
        echo $debugTrace;
    }

    /**
     * Shows the HTML trace.
     *
     * @return string
     */
    public function getDebugTrace($trace)
    {
        if (!\DebugHelper::isCli()) {
            Styles::showHeader('showtrace');
            Styles::showHeader('objectToHtml');
        }
        $debugBacktrace = array();
        foreach ($trace as $item) {
            $step = array();

            if (isset($item['function'])) {
                $step['function'] = isset($item['class'])
                    ? $item['class'] . '::' . $item['function']
                    : $item['function'];
            } else {
                $step['function'] = 'include: ' . $item['include_filename'];
            }
            $step['params'] = isset($item['params'])
                ? count($item['params'])
                : isset($item['args'])
                    ? count($item['args']) :
                    '-';
            $step['file'] = isset($item['file']) ? $item['file'] : '-';
            $step['line'] = isset($item['line']) ? $item['line'] : '-';
            $debugBacktrace[] = $step;
        }

        if (\DebugHelper::isCli()) {
            $debugBacktrace = "Showtrace in " . $this->array2Text($debugBacktrace);
        } else {
            $debugBacktrace = $this->array2Html($debugBacktrace, 'showtrace');
            $debugBacktrace = "<pre>$debugBacktrace</pre>";
        }
        return $debugBacktrace;
    }
}
