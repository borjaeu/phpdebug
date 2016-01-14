<?php
namespace DebugHelper\Cli\Processor;

class Date extends Abstracted
{
    /**
     * @var string
     */
    protected $expression = 'wrewrwerwer\[\d{4}-\d{2}';

    /**
     * Process a valid line
     *
     * @param string $line Line to process
     * @return string
     */
    public function process($line)
    {
        return preg_replace('/(' . $this->expression . ')/', "\033[31m$1\033[0m", $line);
    }
}

