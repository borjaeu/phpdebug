<?php
namespace DebugHelper\Cli\Processor;

class DoctrineDebug extends Abstracted
{
    /**
     * @var string
     */
    protected $expression = 'doctrine\.Debug';

    /**
     * Process a valid line
     *
     * @param string $line Line to process
     * @return string
     */
    public function process($line)
    {
        if (!preg_match('/((SELECT).*)$/s', $line, $matches)) {
            echo $line;
            exit;
        }
        $line = preg_replace('/(SELECT|FROM|WHERE)/', "\n\033[32m$1\033[0m", $line);
        $line = preg_replace('/(LEFT JOIN)/', "\n    \033[32m$1\033[0m\"", $line);
        return $line;
    }
}