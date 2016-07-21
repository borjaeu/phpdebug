<?php
namespace DebugHelper\Tools\Helper;

class CommandParameters
{
    /**
     * @var array
     */
    protected $parameters;

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

            if (empty($this->parameters[$parameter])) {
                throw new \UnexpectedValueException('Missing parameter ' . $parameter);
            }

            return $this->parameters[$parameter];
        }, $command);

        return $command;
    }

}