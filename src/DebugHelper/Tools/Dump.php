<?php
namespace DebugHelper\Tools;

use DebugHelper\Styles;
use DebugHelper\Tools\Model\Position;

class Dump
{
    /**
     * Max depth for output objects
     *
     * @var int
     */
    protected $depth = 5;

    /**
     * @param int $depth
     * @return Dump
     */
    public function setDepth($depth)
    {
        $this->depth = $depth;
        return $this;
    }

    public function expand()
    {
        \DebugHelper::disable(\DebugHelper::OPTION_DUMP_COLLAPSED);
        return $this;
    }

    /**
     * Displays the data passed as information
     */
    public function dump()
    {
        $output = new Output();
        $output->open();
        $args = func_get_args();

        foreach ($args as $arg) {
            $output->dump($arg, $this->depth);
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

        $output = new Output();

        $output->table($debugTrace);
    }

    /**
     * Returns the trace.
     *
     * @return array
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
        return $debugBacktrace;
    }

    /**
     * Exports the filter debug info.
     *
     * @return Position
     */
    protected function getCallerInfo()
    {
        $trace = debug_backtrace(false);

        $item = ['file'=> '', 'line' => 0];
        foreach ($trace as $item) {
            if (isset($item['file'])) {
                if (preg_match('/DebugHelper/', $item['file'])) {
                    continue;
                }
            } elseif (isset($item['class']) && preg_match('/DebugHelper/', $item['class'])) {
                continue;
            }
            break;
        }

        if (!isset($item['file'], $item['line'])) {
            var_dump($trace);
            exit;
        }

        $position = new Position($item['file'], $item['line']); // Demo
        $position->setCall($item['function']);
        return $position;
    }
}
