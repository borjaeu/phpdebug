<?php
namespace DebugHelper\Tools\Model;

class Position
{
    protected $file;

    protected $line;

    protected $call;

    protected $source;

    public function __construct($file, $line)
    {
        $this->file = $file;
        $this->line = $line;
        $this->getCodeLineInfo();
    }

    public function setCall($call)
    {
        $this->call = $call;
    }

    /**
     * @return mixed
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @return mixed
     */
    public function getLine()
    {
        return $this->line;
    }

    /**
     * @return mixed
     */
    public function getCall()
    {
        return $this->call;
    }

    /**
     * @return mixed
     */
    public function getSource()
    {
        return $this->source['source'];
    }

    /**
     * Gets information about a code file by opening the file and reading the PHP code.
     *
     * @return array
     */
    protected function getCodeLineInfo()
    {
        $result = array(
            'class' => false,
            'method' => false,
            'source' => ''
        );

        if (!is_file($this->file)) {
            return;
        }

        $fp = fopen($this->file, 'r');
        $line_no = 0;
        $class_reg_exp = '/^\s*(abstract)?\s*[cC]lass\s+([^\s]*)\s*(extends)?\s*([^\s]*)/';
        $function_reg_exp = '/^\s+(.*)function\s+([^\(]*)\((.*)\)/';
        while ($line_no++ < $this->line) {
            $result['source'] = fgets($fp);
            if (preg_match($class_reg_exp, $result['source'], $matches)) {
                $result['class'] = $matches[2];
            } elseif (preg_match($function_reg_exp, $result['source'], $matches)) {
                $result['method'] = $matches[2];
            }
        }
        $this->source = $result;
    }
}
