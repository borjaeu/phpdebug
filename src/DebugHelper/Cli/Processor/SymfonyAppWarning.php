<?php
namespace DebugHelper\Cli\Processor;

class SymfonyAppWarning extends Clean
{
    /**
     * @var string
     */
    protected $expression = 'app\.Warning';

    /**
     * Process a valid line
     *
     * @param string $line Line to process
     * @return string
     */
    public function process($line)
    {
        if (preg_match('/\{.*\}/s', $line, $matches)) {
            print_r($matches[0]);
            $json = json_decode($matches[0]);
            if ($json !== null) {
                $line = preg_replace('/\{.*\}/s', json_encode($json, JSON_PRETTY_PRINT), $line);
            }
        }
        return $line;
    }
}