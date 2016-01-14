<?php
namespace DebugHelper\Tools;

use DebugHelper\Tools\Model\Position;

class Exception extends Abstracted
{
    /**
     * Begins the trace to watch where the code goes.
     *
     * @param Exception $exception
     */
    public function exception($exception)
    {
        if (!$exception instanceof \Exception) {
            k_die(); // The given exception is not a valid one...
        }

        $exceptionName = get_class( $exception );
        $exceptionFile = $exception->getFile();
        $exceptionLine = $exception->getLine();
        $position = new Position($exceptionFile, $exceptionLine);

        $exceptionTrace = $exception->getTrace();
        $exceptionTrace = \DebugHelper::getClass('\DebugHelper\Tools\Dump')->getDebugTrace($exceptionTrace);

        /*echo <<<EXCEPTION
Exception thrown:
$exceptionName
<a href="codebrowser:$exceptionFile:$exceptionLine">$exceptionFile:$exceptionLine</a>
$exceptionTrace
EXCEPTION;
*/
        \DebugHelper::getClass('\DebugHelper\Tools\Output')->dump($position, $exception->getMessage());
        \DebugHelper::getClass('\DebugHelper\Tools\Dump')->dump($exceptionTrace, 3);
    }
}