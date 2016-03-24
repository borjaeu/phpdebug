<?php
namespace DebugHelper\Tools;

use DebugHelper\Styles;
use DebugHelper\Tools\Model\Position;

class Output extends Abstracted
{
    /**
     * @var integer
     */
    protected $start = false;

    protected $open = false;

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
            if (isset($item['class']) && preg_match('/DebugHelper/', $item['class'])) {
                continue;
            }
            if (isset($item['file']) && preg_match('/DebugHelper/', $item['file'])) {
                continue;
            }
            break;
        }

        $position = new Position($item['file'], $item['line']);
        $position->setCall($item['function']);
        return $position;
    }

    /** Displays the data passed as information.
     *
     * @return Output
     */
    public function open()
    {
        $position = $this->getCallerInfo();
        $this->open = true;
        if ($this->start === false) {
            $this->start = microtime(true);
            $split = 0;
        } else {
            $split = microtime(true) - $this->start;
        }
        $split = number_format($split, 6);

        Styles::showHeader('dump', 'objectToHtml');


        $pos = self::getCallerDetails($position);
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
        $mustClose = false;
        if (!$this->open) {
            $mustClose = true;
            $this->open();
        }
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
        if ($mustClose) {
            $this->close();
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
        $this->open = false;
        if (\DebugHelper::isCli()) {
            echo "====================================\n";
        } else {
            echo <<<DEBUG

</div>

DEBUG;
        }
    }
}
