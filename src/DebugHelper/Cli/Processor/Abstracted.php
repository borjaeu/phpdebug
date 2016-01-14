<?php
namespace DebugHelper\Cli\Processor;

abstract class Abstracted
{
    /**
     * @var string
     */
    protected $expression;

    final public function matches($line)
    {
        $value = preg_match('/' . $this->expression . '/i', $line);
        return $value;
    }

    /**
     * Process a valid line
     *
     * @param string $line Line to process
     * @return string
     */
    abstract public function process($line);
}