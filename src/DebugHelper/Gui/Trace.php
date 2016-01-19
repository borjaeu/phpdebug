<?php
namespace DebugHelper\Gui;

class Trace
{
    protected $id;
    protected $coverage_file;
    protected $trace_file;
    protected $coverage;
    protected $totalLines;

    /**
     * Runs the execution of the code shown.
     */
    public function renderLoadsHtml()
    {
        $this->coverage = $this->loadCoverage();
        $this->renderCode($this->getLine());
    }

    /**
     * Sets the value of file.
     *
     * @param string $file The path of the file containing the debug.
     * @return Trace
     * @throws \Exception If the file is not valid.
     */
    public function setFile($file)
    {
        $this->id = $file;

        $this->coverage_file = \DebugHelper::getDebugDir() . $file . '.cvg';
        if (!is_file($this->coverage_file)) {
            throw new \Exception("Error Processing file {$this->coverage_file}");
        }

        $this->trace_file = \DebugHelper::getDebugDir() . $file . '.xt.clean';
        if (!is_file($this->trace_file)) {
            $this->trace_file = \DebugHelper::getDebugDir() . $file . '.xt';
            if (!is_file($this->trace_file)) {
                throw new \Exception("Error Processing file {$this->trace_file}");
            }

        }
        return $this;
    }

    /**
     * Gets the coverage from the xdebug output file.
     *
     * @return array
     */
    protected function loadCoverage()
    {
        return json_decode(file_get_contents($this->coverage_file), true);
    }

    /**
     * Renders the code in the given line
     *
     * @param integer $targetLineNo Position of the trace
     */
    protected function renderCode($targetLineNo)
    {
        $this->totalLines = 0;
        $trace_lines = $this->getLines($this->trace_file, $targetLineNo, 30);

        $context = '';
        if (preg_match('/\s+(?P<file>\/.*?:)(\d+)/', $trace_lines[$targetLineNo], $matches)) {
            $context = $this->getContext($this->trace_file, $targetLineNo, $matches['file']);
        }
        $navigation = $this->getTraceBreadCrumbs($this->trace_file, $targetLineNo);

        $template = new Template();
        $template->assign('id', $this->id);
        $template->assign('selected_trace', $targetLineNo);
        $template->assign('trace_lines', $trace_lines);
        $template->assign('total_lines', $this->totalLines);
        $template->assign('progress', floor(100 * $targetLineNo / $this->totalLines));
        $template->assign('navigation', $navigation);
        $template->assign('context', $context);
        $template->assign('section', 'trace');
        $template->assign('code_lines', $this->getCodeLines($trace_lines[$targetLineNo]));
        echo $template->fetch('trace');
    }

    /**
     * @param $line
     * @return array|bool
     */
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

    /**
     * Returns an array with the lines in the specified file.
     *
     * @param string $file The file to return the lines from.
     * @param integer $target_line_no Number of the line to retrieve.
     * @param integer $margin Number of lines to retrieve after and before the current line.
     * @return array
     */
    protected function getLines($file, $target_line_no, $margin)
    {
        if (!is_file($file)) {
            return array();
        }
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

    /**
     * Returns an the breadcrumbs to reach the line we are requesting.
     *
     * @param string $file The file to return the lines from.
     * @param integer $target_line_no Number of the line to retrieve.
     * @return array
     */
    protected function getTraceBreadCrumbs($file, $target_line_no)
    {
        $lineRegExp = '/(\s+)->\s*([^\(]*?(\w+))\(/i';

        if (!is_file($file)) {
            return array();
        }
        $fp = fopen($file, 'r');
        $line_no = 1;
        $history = array();

        $pos = array('depth' => 1234);

        while ($line_no <= $target_line_no) {
            $line = fgets($fp);
            if (preg_match($lineRegExp, $line, $matches)) {
                $pos = array(
                    'depth' => strlen($matches[1]),
                    'line'  => $line_no,
                    'call'  => $matches[2],
                    'name'  => $matches[3]
                );
                for ($i = count($history) - 1; $i >= 0; $i--) {
                    if ($history[$i]['depth'] >= $pos['depth']) {
                        unset($history[$i]);
                    }
                }
                $history = array_values($history);
                $history[] = $pos;
            }
            $line_no++;
        }

        array_pop($history);

        $actualDepth = $pos['depth'];

        $next = false;
        while (!feof($fp)) {
            $line = fgets($fp);
            if (preg_match($lineRegExp, $line, $matches)) {
                $depth = strlen($matches[1]);
                if ($depth <= $actualDepth) {
                    $next = array(
                        'depth' => $depth,
                        'line' => $line_no,
                        'call' => $matches[2],
                        'name'  => $matches[3]
                    );
                    break;
                }
            }
            $line_no++;
        }
        fclose($fp);
        return array(
            'breadcrumbs'   => $history,
            'next'          => $next
        );
    }

    /**
     * Get the trace context for the lines in the source.
     *
     * @param string $file The file to return the lines from.
     * @param integer $target_line_no Number of the line to retrieve.
     * @param string $source_file The file to return the lines from.
     * @return array
     */
    protected function getContext($file, $target_line_no, $source_file)
    {
        if (!is_file($file)) {
            return array();
        }
        $fp = fopen($file, 'r');
        $line_no = 1;

        $context = array();
        while ($line_no <= $target_line_no) {
            $line = fgets($fp);
            if (strpos($line, $source_file)) {
                preg_match('/(?P<file>\/.*?:)(?P<line>\d+)/', $line, $matches);
                $context[$matches['line']] = $line_no;
            }
            $line_no++;
        }
        while (!feof($fp)) {
            $line = fgets($fp);
            if (strpos($line, $source_file)) {
                preg_match('/(?P<file>\/.*?:)(?P<line>\d+)/', $line, $matches);
                $context[$matches['line']] = $line_no;
            }
            $line_no++;
        }
        $this->totalLines = $line_no - 1;
        fclose($fp);
        return $context;
    }
}
