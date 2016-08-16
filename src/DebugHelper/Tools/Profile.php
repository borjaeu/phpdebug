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
     * Start position of the profiling
     *
     * @var string
     */
    protected $start_position;

    /**
     * Singleton
     *
     * @return Log
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    /**
     * Begins the trace to watch where the code goes.
     */
    public function profile()
    {
        xhprof_enable();
        $this->start_position = $this->getCallerDetails(2, false);
    }

    /**
     * Shows a coverage report of the trace since the watch() method was called.
     *
     * @return string
     */
    public function profileReport($file = false)
    {
        $pos = $this->getCallerDetails(2, false);

        $xhprof_data = xhprof_disable();
        uasort($xhprof_data, array($this, 'compareItems'));

        $output = $this->start_position . $pos . '<pre class="profile_report">';
        foreach ($xhprof_data as $method => $stats) {
            $ratio = number_format(($stats['wt'] / $this->max_time) * 100, 2);
            if ($ratio < 1) {
                continue;
            }
            $output .= <<<ITEM
<div><div class="label"><span>{$stats['wt']}</span>$method ({$stats['ct']})</div><div class="rate" style="width:{$ratio}%"></div></div>
ITEM;

        }
        $output .= '</pre>';
        if ($file) {
            $output = Styles::getHeader('profileReport') . $output;
            $filename = \DebugHelper::get('debug_dir').uniqid().'.html';
            file_put_contents($filename, $output);
        } else {
            Styles::showHeader('profileReport');
            echo $output;
        }
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
