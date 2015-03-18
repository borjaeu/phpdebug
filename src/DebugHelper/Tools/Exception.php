<?php
namespace DebugHelper\Tools;

class Exception extends Abstracted
{
    /**
     * Begins the trace to watch where the code goes.
     *
     * @param Exception $exception
     */
    public function exception( $exception )
    {
        $exception_name = get_class( $exception );
        $file = $exception->getFile();
        $line = $exception->getLine();
        $trace = $exception->getTrace();

        $debug_backtrace = '<table>';
        foreach ($trace as $item) {
            if (!isset( $item['file'] )) {
                continue;
            }
            if (isset( $item['function'] )) {
                $function = isset( $item['class'] ) ? $item['class'] . '::' . $item['function'] : $item['function'];
            } else {
                $function = 'inlcude: ' . $item['include_filename'];
            }
            $file = $item['file'];

            $debug_backtrace
                .= <<<ROW
	<tr class="">
		<td><a href="codebrowser:{$item['file']}:{$item['line']}">$file</a></td>
		<td>{$item['line']}</td>
		<td>$function()</td>
	</tr>

ROW;
        }
        $debug_backtrace .= "\n</table>-------- END: TRACE --------\n";

        echo <<<EXCEPTION
$exception_name
<a href="codebrowser:$file:$line">$file:$line</a>
$debug_backtrace
EXCEPTION;

        //var_dump( $exception );
        \DebugHelper::dump( $exception->getMessage() );
    }

}