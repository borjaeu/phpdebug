<?php
namespace DebugHelper\Tools\Helper;

/**
 * Class CommandParameters
 *
 * Replace command parameters with values.
 * commnad <required_parameter> [<optional_parameter>] [additional=<optional_parameter>]
 *
 * @package DebugHelper\Tools\Helper
 */
class CommandParameters
{
    /**
     * @var array
     */
    protected $parameters;

    /**
     * @param string $command
     * @param array  $parameters
     * @return string
     */
    public function parse($command, array $parameters)
    {
        $this->parameters = $parameters;

        $command = $this->replaceOptionalParameters($command);

        $command = $this->replaceRequiredParameters($command);

        return $command;
    }

    /**
     * @param string $command
     * @return string
     */
    private function replaceOptionalParameters($command)
    {
        $command = preg_replace_callback('/\[(?P<optional>[^\]]+)]/', function ($match) {
            try {
                $chunk = $this->replaceRequiredParameters($match['optional']);
            } catch (\UnexpectedValueException $exception) {
                return '';
            }

            return $chunk;
        }, $command);

        return $command;
    }

    /**
     * @param string $command
     * @return string
     */
    private function replaceRequiredParameters($command)
    {
        $command = preg_replace_callback('/<(?P<parameter>\w+)>/', function ($match) {
            $parameter = $match['parameter'];

            if (!isset($this->parameters[$parameter])) {
                throw new \UnexpectedValueException('Missing parameter '.$parameter);
            }

            return $this->parameters[$parameter];
        }, $command);

        return $command;
    }
}
