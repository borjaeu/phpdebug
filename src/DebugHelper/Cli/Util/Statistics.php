<?php
namespace DebugHelper\Cli\Util;

class Statistics
{
    /**
     * Statistics extracted from the data
     *
     * @var array
     */
    protected $statistics;

    /**
     * Entries names for the statistics
     *
     * @var array
     */
    protected $entries;

    public function __construct()
    {
        $this->statistics = [];
        $this->entries = [];
    }

    /**
     * @param string $element
     * @return mixed
     */
    public function getStatistics($element = '')
    {
        if (!empty($element)) {
            $steps = explode('.', $element);
            return$this->getElement($steps);
        }
        return $this->statistics;
    }

    /**
     * @param      $key
     * @param      $name
     * @param null $default
     * @return mixed|null
     */
    public function get($key, $name = null, $default = null)
    {
        $steps = explode('.', $key);
        if (!is_null($name)) {
            $steps[] = $name;
        }
        return $this->getElement($steps, $default);
    }

    /**
     * @param      $key
     * @param  $value
     * @throws \Exception
     */
    public function set($key, $name, $value)
    {
        $steps = explode('.', $key);
        $steps[] = $name;
        $this->registerEntry($key);
        $this->setElement($steps, $value);
    }

    /**
     * Sort all aggregations
     */
    public function sort()
    {
        foreach ($this->entries as $entry) {
            $steps = explode('.', $entry);
            $elements = $this->getElement($steps);
            uasort($elements, function ($a, $b) {
                if ($a == $b) {
                    return 0;
                }
                return ($a < $b) ? 1 : -1;
            });
            $this->setElement($steps, $elements);
        }
    }

    /**
     * @param      $key
     * @param null $value
     * @throws \Exception
     */
    public function increment($key, $name = null, $step = 1)
    {
        $steps = explode('.', $key);
        if (!is_null($name)) {
            $steps[] = $name;
            $this->registerEntry($key);
        }
        $currentValue = $this->getElement($steps);

        if (is_null($currentValue)) {
            $this->setElement($steps, $step);
        } elseif (is_numeric($currentValue)) {
            $this->setElement($steps, $currentValue + $step);
        } else {
            throw new \Exception('Invalid value for entry for ' . $key . ' ' . print_r($currentValue, true));
        }
    }

    /**
     * Register an entry for statistics
     *
     * @param string $key Id of the entry to register
     * @param string $type Type of entry
     */
    protected function registerEntry($key)
    {
        if (!in_array($key, $this->entries)) {
            $this->entries[] = $key;
        }
    }

    /**
     * Gets an element by its key
     *
     * @param array $steps Levels to reach to the element
     * @return null|mixed
     */
    protected function getElement(array $steps, $default = null)
    {
        $data = $this->statistics;
        while (!empty($steps)) {
            $step = array_shift($steps);
            if (isset($data[$step])) {
                $data = $data[$step];
            } else {
                return $default;
            }
        }
        return $data;
    }

    /**
     * @param string $path Levels to reach to the element
     * @param $value
     */
    protected function setElementByPath($path, $value)
    {
        $steps = explode('.', $path);
        $this->setSubElement($steps, $this->statistics, $value);
    }

    /**
     * @param array $steps Levels to reach to the element
     * @param $value
     */
    protected function setElement(array $steps, $value)
    {
        $this->setSubElement($steps, $this->statistics, $value);
    }

    /**
     * @param array $levels Levels to reach to the element
     * @param array $data Data to set
     * @param $value
     */
    protected function setSubElement(array $steps, & $data, $value)
    {
        $step = array_shift($steps);
        if (empty($steps)) {
            $data[$step] = $value;
        } else {
            if (!isset($data[$step])) {
                $data[$step] = [];
            }
            $this->setSubElement($steps, $data[$step], $value);
        }
    }
}
