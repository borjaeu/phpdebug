<?php
namespace DebugHelper\Tools;

use DebugHelper\Tools\Model\Position;

/**
 * Class Exception
 * @package DebugHelper\Tools
 */
class Exception
{
    /**
     * Begins the trace to watch where the code goes.
     *
     * @param \Exception $exception
     */
    public function exception($exception)
    {
        if (!$exception instanceof \Exception) {
            \DebugHelper::die(); // The given exception is not a valid one...
        }

        $exceptionName = get_class($exception);
        $exceptionFile = $exception->getFile();
        $exceptionLine = $exception->getLine();

        $exceptionTrace = $exception->getTrace();
        $exceptionTrace = \DebugHelper::dump()->getDebugTrace($exceptionTrace);

        $output = new Output();
        $position = new Position($exceptionFile, $exceptionLine);
        $pos = $output->getCallerDetails($position);
        $output->open();
        $output->dump($pos);
        $output->dump($exceptionName.': '.$exception->getMessage());
        $output->dump($exceptionTrace);
        $output->close();
    }
}
