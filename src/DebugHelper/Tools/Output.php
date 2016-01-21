<?php
namespace DebugHelper\Tools;

use DebugHelper\Styles;
use DebugHelper\Tools\Model\Position;

class Output extends Abstracted
{
    /**
     * Exports the filter debug info.
     *
     * @param array $pos Position information where the dump is made
     * @return string
     */
    protected function getCallerDetails(Position $position)
    {
        $id = uniqid();

        Styles::showHeader('getCallers');

        $line = $position->getLine();
        $file = $position->getFile();
        $call = $position->getCall();
        $source = str_replace(array("\t", ' '), array('->', '.'), $position->getSource());

        if (\DebugHelper::isCli()) {
            return <<<POS
caller: {$pos['file']}:{$pos['line']} in {$pos['class']}{$pos['method']} "{$pos['line']}"

POS;
        } else {
            $filename = basename($file);
            return <<<POS
<a class="debug_caller" name="$id" href="codebrowser:{$file}:{$line}" title="in {$call}">{$source}
    <span class="line">{$filename}::{$line}</span>
</a>

POS;
        }
    }

    /**
     * Displays the data passed as information.
     *
     * @param array $pos Position information where the dump is made
     * @param mixed $data Information to be dumped to the browser.
     */
    public function dump(Position $pos, $data = null, $maxDepth = 5)
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

        if (!is_null($data) && !is_string($data)) {
            $data = $this->objectToHtml($data, $maxDepth);
        }
        $pos = $this->getCallerDetails($pos);
        $id = uniqid();
        if (\DebugHelper::isCli()) {
            echo "[Dump] var, $pos====================================\n$data====================================\n";
        } else {
            if (!is_null($data)) {
                $data = "<div class=\"data\">{$data}</div>";
            } else {
                $data = '';
            }

            echo <<<DEBUG

<div id="$id" class="debug_dump">
    <div class="header">
        <span class="timer">$split</span>
        <span class="code">$pos</span>
     </div>
     {$data}
</div>

DEBUG;
        }
    }
}
