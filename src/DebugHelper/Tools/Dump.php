<?php
namespace DebugHelper\Tools;

use DebugHelper\Styles;

class Dump extends Abstracted
{
    /**
     * Depth of the method caller
     *
     * @var int
     */
    protected $depth = 2;

    /**
     * Max depth for output objects
     *
     * @var int
     */
    protected $maxDepth = 5;

    /**
     * @param int $depth
     * @return Dump
     */
    public function setDepth($depth)
    {
        $this->depth = $depth;
        return $this;
    }

    /**
     * @param int $maxDepth
     * @return Dump
     */
    public function setMaxDepth($maxDepth)
    {
        $this->maxDepth = $maxDepth;
        return $this;
    }

    /**
     * Displays the data passed as information
     */
    public function dump()
    {
        $pos = $this->getCallerInfo($this->depth);

        $output = new Output();
        $output->open($pos);
        $args = func_get_args();

        foreach ($args as $arg) {
            $output->dump($arg, $this->maxDepth);
        }
        $output->close();
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

//            $step['params'] = isset($item['params'])
//                ? count($item['params'])
//                : isset($item['args'])
//                    ? count($item['args']) :
//                    '-';
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
