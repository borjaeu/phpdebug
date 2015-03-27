<?php
namespace DebugHelper\Tools;

use DebugHelper\Styles;

class Profile extends Abstracted
{
    /**
     * File to save the trace information.
     *
     * @var integer
     */
    protected $max_time = 0;

    /**
     * Begins the trace to watch where the code goes.
     */
    public function profile()
    {
        xhprof_enable();
        $pos = $this->getCallerDetails(2, false);
        echo $pos;
    }

    /**
     * Shows a coverage report of the trace since the watch() method was called.
     *
     * @return string
     */
    public function profileReport()
    {
        $pos = $this->getCallerDetails(2, false);

        Styles::showHeader('profileReport');

        $xhprof_data = xhprof_disable();
        uasort($xhprof_data, array($this, 'compareItems'));

        echo $pos . '<pre class="profile_report">';
        foreach ($xhprof_data as $method => $stats) {
            $ratio = number_format(($stats['wt'] / $this->max_time) * 100, 2);
            if ($ratio < 1) {
                continue;
            }
            echo <<<ITEM
<div><div class="label"><span>{$stats['wt']}</span>$method ({$stats['ct']})</div><div class="rate" style="width:{$ratio}%"></div></div>
ITEM;

        }
        echo '</pre>';
    }

    protected function compareItems($item_a, $item_b)
    {
        $this->max_time = max($item_a['wt'], $item_b['wt'], $this->max_time);
        if ($item_a['wt'] == $item_b['wt']) {
            return 0;
        }
        return $item_a['wt'] < $item_b['wt'] ? 1 : -1;
    }
}