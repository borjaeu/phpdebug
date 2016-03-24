<?php
namespace DebugHelper\Tools\Helper;

class Trace
{
    /**
     * @return array
     */
    public function getTrace()
    {
        $trace = xdebug_get_function_stack();

        foreach ($trace as & $item) {

            if (isset($item['function'])) {
                $item['call'] = isset($item['class']) ? $item['class'] . '::' . $item['function'] : $item['function'];
            } else {
                $item['call'] = 'inlcude: ' . $item['include_filename'];
            }
        }
        return $trace;
    }
}
