<?php
namespace DebugHelper\Tools;

class Arrays extends Abstracted
{
    protected static $instance;
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
     * Look for information inside an array.
     *;
     * @param array $data First array to compare.
     * @param string $needle Data to search in the array.
     */
    public function search($data, $needle, $path = array())
    {
        if (is_array($data)) {
            foreach ($data as $key => $sub_data) {
                if (false !== $result = $this->search($sub_data, $needle, array_merge($path, array($key)))) {
                    return $result;
                }
            }
        } elseif (is_string($data)) {
            if (preg_match($needle, $data)) {
                return implode('.', $path);
            }
        }
        return false;
    }

    /**
     * Compares to arrays and give visual feedback of the dereferences.
     *
     * @param array $before First array to compare.
     * @param array $after Second array to compare.
     * @param boolean $just_changes Don't show rows that are the same.
     */
    public function compare($before, $after, $just_changes = false)
    {
        if (!(is_array($before) && is_array($after))) {
            return;
        }
        $labels = $this->getLabels();

        $styles = array(
            'equal' => 'CCFFCC',
            'changed' => 'FFCCCC',
            'missing' => '00FFFF',
            'new' => 'FFFFBF'
        );
        $td_style = ' style="padding:3px; border:1px solid #CCCCCC;"';
        $diffs = $this->arrayDiff($before, $after);
        if ($just_changes) {
            foreach ($diffs as $key => $item) {
                if ($item['status'] == 'equal') {
                    unset($diffs[$key]);
                }
            }
        }

        echo $this->getCallerDetails(2);
        echo "<pre><table style=\"border: 1px solid black; margin:5px;\">";
        echo <<<HTML
<tr>
	<th>&nbsp;</th><th>{$labels['first_param']}</th><th>{$labels['second_param']}</th>
</tr>
HTML;
        foreach ($diffs as $key => $options) {
            $value_a = print_r($options['source'], true);
            $value_b = print_r($options['target'], true);
            $status = $options['status'];
            echo <<<HTML
				<tr style="background:#{$styles[$status]};" title="$status"><td$td_style>$key</td><td$td_style>$value_a</td><td$td_style>$value_b</td></tr>
HTML;
        }
        echo '</table></pre>';
        ob_flush();
    }

    /**
     * Returns a definition of changes.
     *
     * @param array $before First array
     * @param array $after Second array
     * @return array
     */
    protected function arrayDiff($before, $after)
    {
        $comparison_result = array();
        foreach ($before as $key => $value_before) {
            if (array_key_exists($key, $after)) {
                if (is_array($after[$key]) && is_array($value_before)) {
                    $sub_comparison = self::arrayDiff($after[$key], $value_before);
                    foreach ($sub_comparison as $subkey => $result) {
                        $comparison_result["$key.$subkey"] = $result;
                    }
                } elseif ($after[$key] == $value_before) {
                    $comparison_result[$key] = array(
                        'source' => $value_before,
                        'target' => $value_before,
                        'status' => 'equal'
                    );
                } else {
                    $comparison_result[$key] = array(
                        'source' => $value_before,
                        'target' => is_null($after[$key]) ? null : $after[$key],
                        'status' => 'changed'
                    );
                }
                unset ($after[$key]);
            } else {
                $comparison_result[$key] = array(
                    'source' => $value_before,
                    'target' => null,
                    'status' => 'missing'
                );
            }
        }
        foreach ($after as $key => $value_after) {
            $comparison_result[$key] = array(
                'source' => null,
                'target' => print_r($value_after, true),
                'status' => 'new'
            );
        }
        return $comparison_result;
    }

    /**
     * Get comparison headers from source code.
     *
     * @return array
     */
    protected function getLabels()
    {
        $source = $this->getCallerSource(5);
        preg_match('/compare\(\s*(?P<first_param>[^,]+)\s*,\s*(?P<second_param>[^,]+)\s*\)/', $source, $params);
        return array(
            'first_param' => isset($params['first_param']) ? $params['first_param'] : 'First array',
            'second_param' => isset($params['second_param']) ? $params['second_param'] : 'Second array'
        );
    }
}
