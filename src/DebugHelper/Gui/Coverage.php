<?php
namespace DebugHelper\Gui;

class Coverage
{
    protected $id;
    protected $coverage_file;
    protected $trace_file;
    protected $coverage;

    public function renderLoadsHtml()
    {
        $this->coverage = $this->loadCoverage();
        $this->renderCode($this->getLine());
    }

    /**
     * Sets the value of file.
     *
     * @param mixed $file the file
     *
     * @return self
     */
    public function setFile($file)
    {
        $this->id = $file;

        $this->coverage_file = \DebugHelper::getDebugDir() . $file . '.cvg';
        if (!is_file($this->coverage_file)) {
            throw new \Exception("Error Processing file {$this->coverage_file}");
        }

        $this->trace_file = \DebugHelper::getDebugDir() . $file . '.xt';
        if (!is_file($this->trace_file)) {
            throw new \Exception("Error Processing file {$this->trace_file}");
        }
        return $this;
    }

    protected function loadCoverage()
    {
        return json_decode(file_get_contents($this->coverage_file), true);
    }

    protected function renderCode($target_line_no)
    {
        $trace_lines = $this->getLines($this->trace_file, $target_line_no, 30);

        $template = new Template();
        $template->assign('id', $this->id);
        $template->assign('selected_trace', $target_line_no);
        $template->assign('trace_lines', $trace_lines);
        $template->assign('code_lines', $this->getCodeLines($trace_lines[$target_line_no]));
        echo $template->fetch('code');
    }

    protected function getCodeLines($line)
    {
        if (!preg_match('/(?P<file>[^\s]+):(?P<line>\d+)$/', $line, $match)) {
            return false;
        }
        $coverage = isset($this->coverage[$match['file']]) ? $this->coverage[$match['file']] : array();
        $lines = $this->getLines($match['file'], $match['line'], 30);
        foreach ($lines as $line_no => $line) {
            $lines[$line_no] = array(
                'path' => $match['file'] . ':' . $line_no,
                'code' => $line,
                'selected' => $line_no == $match['line'],
                'covered' => isset($coverage[$line_no])
            );
        }

        return $lines;
    }

    protected function getLine()
    {
        return isset($_GET['line']) ? $_GET['line'] : 1;
    }

    protected function getLines($file, $target_line_no, $margin)
    {
        $fp = fopen($file, 'r');
        $line_no = 0;
        $lines = array();
        $start = $target_line_no - $margin;
        if ($start < 0) {
            $start = 0;
        }
        while ($line_no < $start) {
            $line_no++;
            fgets($fp);
        }
        for ($i = 0; $i < $margin * 2 + 1; $i++) {
            $line_no++;
            $lines[$line_no] = rtrim(fgets($fp));
        }
        fclose($fp);
        return $lines;
    }

}
