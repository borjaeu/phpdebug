<?php
namespace DebugHelper;

class Cli
{
    protected $options = ['list', 'sequence'];

    /**
     * Execute the command line
     *
     * @param array $arguments Arguments sent to the command line
     * @throws \Exception
     */
    public function execute(array $arguments)
    {
        if (empty($arguments[1])) {
            throw new \Exception('Invalid request');
        }
        $option = $arguments[1];
        if (!in_array($option, $this->options)) {
            throw new \Exception("Unknown options $option");

        }
        $className = '\DebugHelper\Cli\\' . ucfirst($option) . 'Command';
        $executor = new $className($arguments);
        $executor->run();
    }

    /**
     * Get options
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }
}
