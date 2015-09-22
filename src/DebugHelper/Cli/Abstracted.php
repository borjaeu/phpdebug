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
        $this->arguments = $arguments;
    }

    /**
     * Execute the command
     */
    abstract public function run();
}
