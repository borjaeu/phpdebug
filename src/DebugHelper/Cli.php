<?php
namespace DebugHelper;

use DebugHelper\Cli\Abstracted;

/**
 * Class Cli
 * @package DebugHelper
 */
class Cli
{
    /**
     * @var array
     */
    protected $options = ['list', 'sequence', 'clean', 'tail', 'read'];

    /**
     * Execute the command line
     *
     * @param array $arguments Arguments sent to the command line
     * @throws \Exception
     */
    public function execute(array $arguments)
    {
        if (empty($arguments[1])) {
            throw new \Exception('Invalid request:'.PHP_EOL.$this->getMessage());
        }
        $option = $arguments[1];
        if (!in_array($option, $this->options)) {
            throw new \Exception("Unknown options $option");
        }
        $className = '\DebugHelper\Cli\\'.ucfirst($option).'Command';
        /** @var Abstracted $executor */
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

    /**
     * @return string
     */
    protected function getMessage()
    {
        return <<<INFO
 - list
 - sequence
 - clean [file] [--force] [--functions] [--skip-namespace='namespace1, namespace2] [--skip-path='directory1, directory2]
 - tail
 - read
INFO;
    }
}
