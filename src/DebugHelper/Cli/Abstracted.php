<?php
namespace DebugHelper\Cli;

abstract class Abstracted
{
    /**
     * Arguments used in the command
     *
     * @var array
     */
    protected $arguments;

    /**
     * Init class
     *
     * @param array $arguments Arguments sent to the command line
     */
    public function __construct(array $arguments)
    {
        $this->arguments = [];
        foreach ($arguments as $index => $argument) {
            if (preg_match('/--(?P<parameter>[^\s]+?)(=(?P<value>.*))?$/', $argument, $matches)) {
                $parameter = $matches['parameter'];
                $value = isset($matches['value']) ? $matches['value'] : true;
                $this->arguments[$parameter] = $value;
            } else {
                $this->arguments[$index] = $argument;
            }
        }
    }

    /**
     * Execute the command
     */
    abstract public function run();
}
