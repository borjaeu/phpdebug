<?php
namespace DebugHelper\Cli\Util;

class Progress
{
    /**
     * With of the progress bar
     *
     * @var int
     */
    protected $size = 80;

    /**
     * Shows the done program for a the given total
     *
     * @param integer $done Completed amount
     * @param integer $total Total amount upon completion
     * @param string $extra Text to show with the bar
     */
    public function showStatus($done, $total, $extra = '')
    {
        if($done > $total) {
            return;
        }
        $percentage = (double)($done / $total);
        $bar = floor($percentage * $this->size);
        $statusBar = "\r[";
        $statusBar .= str_repeat("=", $bar);
        if($bar < $this->size){
            $statusBar .= ">";
            $statusBar .= str_repeat(" ", $this->size - $bar);
        } else {
            $statusBar .= "=";
        }
        $percentage = number_format($percentage * 100, 0);
        $statusBar .= "] $percentage%  $done/$total";
        echo "$statusBar $extra";
        flush();

        if($done == $total) {
            echo "\n";
        }
    }
}
