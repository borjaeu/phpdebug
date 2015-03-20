<?php
namespace DebugHelper;

class Gui
{
    static public function getTrace($path)
    {
        $trace = new \DebugHelper\Gui\Trace();
        $trace->setFile($path);
        echo $trace->loadHtml();
    }
}