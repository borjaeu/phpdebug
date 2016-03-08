<?php
namespace DebugHelper\Tools;

use DebugHelper\Styles;
use DebugHelper\Tools\Model\Position;

class Output extends Abstracted
{
    protected $start = false;

    /**
     * Exports the filter debug info.
     *
     * @param Position $position Position information where the dump is made
     * @return string
     */
    public function getCallerDetails(Position $position)
    {
        $id = uniqid();

        Styles::showHeader('getCallers');

        $line = $position->getLine();
        $file = $position->getFile();
        $call = $position->getCall();
        $source = str_replace(array("\t", ' '), array('->', '.'), $position->getSource());

        if (\DebugHelper::isCli()) {
            return <<<POS
caller: {$file}:{$line} in {$call} "{$line}"

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

    /** Displays the data passed as information.
     *
     * @param Position $pos Position information where the dump is made
     * @return Output
     */
    public function open(Position $pos)
    {
        if ($this->start === false) {
            $this->start = microtime(true);
            $split = 0;
        } else {
            $split = microtime(true) - $this->start;
        }
        $split = number_format($split, 6);

        Styles::showHeader('dump', 'objectToHtml');


        $pos = self::getCallerDetails($pos);
        $id = uniqid();
        if (\DebugHelper::isCli()) {
            echo "[Dump] var, $pos====================================\n";
        } else {
            echo <<<DEBUG

<div id="$id" class="debug_dump">
    <div class="header">
        <span class="timer">$split</span>
        <span class="code">$pos</span>
     </div>

DEBUG;
        }
        return $this;
    }

    /**
     * Displays the data passed as information.
     *
     * @param mixed $data Information to be dumped to the browser.
     * @param integer $maxDepth Maximum depth for objects
     * @return Output
     */
    public function dump($data, $maxDepth = 5)
    {
        if (!is_null($data) && !is_string($data)) {
            $data = self::objectToHtml($data, $maxDepth);
        }

        if (\DebugHelper::isCli()) {
            echo $data;
        } else {
            if (!is_null($data)) {
                $data = "<div class=\"data\">{$data}</div>";
            } else {
                $data = '';
            }

            echo <<<DEBUG
     {$data}

DEBUG;
        }
        return $this;
    }

    /**
     * Displays the data passed as information.
     *
     * @return Output
     */
    public function close()
    {
        if (\DebugHelper::isCli()) {
            echo "====================================\n";
        } else {
            echo <<<DEBUG

</div>

DEBUG;
        }
    }
}
