<?php
namespace DebugHelper;

class Gui
{
    public static function getTrace($path)
    {
        $trace = new \DebugHelper\Gui\Trace();
        echo $trace->setFile($path)->loadHtml();
    }

    public static function renderLoadsHtml($path)
    {
        $trace = new \DebugHelper\Gui\Trace();
        $trace->setFile($path)->renderLoadsHtml();
    }
}