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
        if (!empty($_GET['search'])) {
            list($targetLineNo, $trace_lines) = $this->getSearchLines($_GET['search']);
        } else {
            if ($targetLineNo < 1) {
                $targetLineNo = 1;
            }
            $trace_lines = $this->getLines($this->trace_file, $targetLineNo, 30);
        }

        $trace_lines = $this->getLinesDetails($trace_lines, $targetLineNo);

        $currentStepInfo = $trace_lines[$targetLineNo];
        $context = [];
        if (isset($currentStepInfo['file'])) {
            $context = $this->getSource($this->trace_file, $targetLineNo, $currentStepInfo['file']);
        }
        $navigation = $this->getTraceBreadCrumbs($this->trace_file, $targetLineNo);

        $template = new Template();
        $template->assign('id', $this->id);
        $template->assign('selected_trace', $targetLineNo);
        $template->assign('trace_lines', $trace_lines);
        $template->assign('total_lines', $this->totalLines);
        if ($this->totalLines) {
            $template->assign('progress', floor(100 * $targetLineNo / $this->totalLines));
        } else {
            $template->assign('progress', false);
        }
        $template->assign('navigation', $navigation);
        $template->assign('context', $context);
        $template->assign('section', 'trace');
        if (isset($currentStepInfo['file'])) {
            $template->assign('code_lines', $this->getCodeLines($currentStepInfo['file'], $currentStepInfo['line']));
        } else {
            $template->assign('code_lines', []);
        }
        echo $template->fetch('trace');
    }

    private function getLinesDetails($traceLines, $targetLineNumber)
    {
        $targetLineInfo = $this->getLineDetails($traceLines[$targetLineNumber]);

        foreach($traceLines as $lineNumber => $line) {
            $traceLines[$lineNumber] = $this->getLineDetails($line);
            $traceLines[$lineNumber]['shared'] = $traceLines[$lineNumber]['file'] == $targetLineInfo['file'];
        }
        return $traceLines;
    }

    /**
     * @param string $search
     * @return array
     */
    private function getSearchLines($search)
    {
        $targetLineNo = 1;
        $searchResults = $this->getLinesForKeyword($this->trace_file, $search);
        $traceLines = [];
        foreach($searchResults as $targetLineNo => $searchResult) {
            $traceLines = array_replace($traceLines, $this->getLines($this->trace_file, $targetLineNo, 5));
        }
        return [$targetLineNo, $traceLines];
    }

    /**
     * @param $line
     * @return array|bool
     */
    protected function getCodeLines($file, $targetLineNumber)
    {
        $coverage = isset($this->coverage[$file]) ? $this->coverage[$file] : array();
        $lines = $this->getLines($file, $targetLineNumber, 30);
        foreach ($lines as $lineNumber => $line) {
            $lines[$lineNumber] = array(
                'path' => $file . ':' . $lineNumber,
                'code' => $line,
                'selected' => $lineNumber == $targetLineNumber,
                'covered' => isset($coverage[$lineNumber])
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
     * @param string $search String to search in the code
     * @return array
     */
    protected function getLinesForKeyword($file, $search)
    {
        if (!is_file($file)) {
            return array();
        }
        $fp = fopen($file, 'r');
        $line_no = 0;
        $lines = array();

        while (!feof($fp)) {
            $line_no++;
            $line = fgets($fp);
            if (strpos($line, $search) !== false) {
                $lines[$line_no] = rtrim($line);
            }
        }
        fclose($fp);
        return $lines;
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

        if (!is_file($file)) {
            return array();
        }
        $fp = fopen($file, 'r');
        $line_no = 1;
        $history = array();

        $pos = array('depth' => 1234);

        while ($line_no <= $target_line_no) {
            $line = fgets($fp);
            $lineInfo = $this->getLineInfo($line);
            if ($lineInfo) {
                $pos = array(
                    'depth' => strlen($lineInfo[1]),
                    'line'  => $line_no,
                    'call'  => $lineInfo[2],
                    'name'  => $lineInfo[3]
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
            $lineInfo = $this->getLineInfo($line);
            if ($lineInfo) {
                $depth = strlen($lineInfo[1]);
                if ($depth <= $actualDepth) {
                    $next = array(
                        'depth' => $depth,
                        'line' => $line_no,
                        'call' => $lineInfo[2],
                        'name'  => $lineInfo[3]
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
    protected function getSource($file, $target_line_no, $source_file)
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

    private function getLineInfo($line)
    {
        $lineRegExp = '/(\s+)->\s*([^\(]*?(\w+))\(/i';

        if (preg_match($lineRegExp, $line, $matches)) {
            return $matches;
        }
        return false;
    }

    /**
     * Extract information from a trace line
     *
     * @param string $line Line to process
     * @return bool
     */
    protected function getLineDetails($line)
    {
        $reg_exp = '/(?P<time>\d+\.\d+)\s+(?P<memory>\d+)(?P<depth>\s+)->\s+(?P<call>.*)\s+(?P<path>[^\s]+?)(:(?P<line>\d+))?$/';
        if (preg_match($reg_exp, $line, $matches)) {
            return [
                'depth'         => ceil(strlen($matches['depth']) / 2),
                'indent'        => $matches['depth'],
                'path_length'   => count(explode('/', $matches['path'])),
                'time'          => floatval($matches['time']),
                'memory'        => $matches['memory'],
                'call'          => $matches['call'],
                'file'          => $matches['path'],
                'line'          => $matches['line']
            ];
        } else {
            return false;
        }
    }
}
