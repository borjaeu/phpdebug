<?php
namespace DebugHelper\Gui;
use DebugHelper\Tools\Output;

/**
 * Class Trace
 * @package DebugHelper\Gui
 */
class Trace
{
    protected $id;

    /**
     * @var string
     */
    protected $coverageFile;

    /**
     * @var string
     */
    protected $traceFile;

    protected $coverage;

    /**
     * @var int
     */
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

        $this->coverageFile = \DebugHelper::get('debug_dir').$file.'.cvg';
        if (!is_file($this->coverageFile)) {
            throw new \Exception("Error Processing file {$this->coverageFile}");
        }

        $this->traceFile = \DebugHelper::get('debug_dir').$file.'.xt.clean';

        if (!is_file($this->traceFile)) {
            $this->traceFile = \DebugHelper::get('debug_dir').$file.'.xt';
            if (!is_file($this->traceFile)) {
                throw new \Exception("Error Processing file {$this->traceFile}");
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
        return json_decode(file_get_contents($this->coverageFile), true);
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
            list($targetLineNo, $traceLines) = $this->getSearchLines($_GET['search']);
        } else {
            if ($targetLineNo < 1) {
                $targetLineNo = 1;
            }
            $traceLines = $this->getLines($this->traceFile, $targetLineNo, 30);
        }

        $traceLines = $this->getLinesDetails($traceLines, $targetLineNo);

        $currentStepInfo = $traceLines[$targetLineNo];
        $context = [];
        if (isset($currentStepInfo['file'])) {
            $context = $this->getSource($this->traceFile, $targetLineNo, $currentStepInfo['file']);
        }
        $navigation = $this->getTraceBreadCrumbs($this->traceFile, $targetLineNo);

        $template = new Template();
        $template->assign('id', $this->id);
        $template->assign('selected_trace', $targetLineNo);
        $template->assign('trace_lines', $traceLines);
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

    /**
     * @param $traceLines
     * @param $targetLineNumber
     * @return mixed
     */
    private function getLinesDetails($traceLines, $targetLineNumber)
    {
        $targetLineInfo = $this->getLineDetails($traceLines[$targetLineNumber]);

        foreach ($traceLines as $lineNumber => $line) {
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
        $searchResults = $this->getLinesForKeyword($this->traceFile, $search);
        $traceLines = [];
        foreach ($searchResults as $targetLineNo => $searchResult) {
            $traceLines = array_replace($traceLines, $this->getLines($this->traceFile, $targetLineNo, 5));
        }

        return [$targetLineNo, $traceLines];
    }

    /**
     * @param string $file
     * @param int    $targetLineNumber
     * @return array
     */
    private function getCodeLines($file, $targetLineNumber)
    {
        $output = new Output();
        $coverage = isset($this->coverage[$file]) ? $this->coverage[$file] : array();
        $lines = $this->getLines($file, $targetLineNumber, 30);
        foreach ($lines as $lineNumber => $line) {
            $lines[$lineNumber] = [
                'path' => $file.':'.$lineNumber,
                'code' => $line,
                'link' => $output->buildUrl($file, $lineNumber),
                'selected' => $lineNumber == $targetLineNumber,
                'covered' => isset($coverage[$lineNumber]),
            ];
        }

        return $lines;
    }

    /**
     * @return int
     */
    private function getLine()
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
    private function getLinesForKeyword($file, $search)
    {
        if (!is_file($file)) {
            return array();
        }
        $fp = fopen($file, 'r');
        $lineNo = 0;
        $lines = array();

        while (!feof($fp)) {
            $lineNo++;
            $line = fgets($fp);
            if (strpos($line, $search) !== false) {
                $lines[$lineNo] = rtrim($line);
            }
        }
        fclose($fp);

        return $lines;
    }

    /**
     * Returns an array with the lines in the specified file.
     *
     * @param string $file The file to return the lines from.
     * @param integer $targetLineNo Number of the line to retrieve.
     * @param integer $margin Number of lines to retrieve after and before the current line.
     * @return array
     */
    private function getLines($file, $targetLineNo, $margin)
    {
        if (!is_file($file)) {
            $handlerSource = \DebugHelper::get('handler_source');
            $handlerTarget = \DebugHelper::get('handler_target');
            $file = str_replace($handlerTarget, $handlerSource, $file);
            if (!is_file($file)) {
                return array();
            }
        }
        $fp = fopen($file, 'r');
        $lineNo = 0;
        $lines = array();
        $start = $targetLineNo - $margin;
        if ($start < 0) {
            $start = 0;
        }
        while ($lineNo < $start) {
            $lineNo++;
            fgets($fp);
        }
        for ($i = 0; $i < $margin * 2 + 1; $i++) {
            $lineNo++;
            $lines[$lineNo] = rtrim(fgets($fp));
        }
        fclose($fp);

        return $lines;
    }

    /**
     * Returns an the breadcrumbs to reach the line we are requesting.
     *
     * @param string $file The file to return the lines from.
     * @param integer $targetLineNo Number of the line to retrieve.
     * @return array
     */
    protected function getTraceBreadCrumbs($file, $targetLineNo)
    {

        if (!is_file($file)) {
            return array();
        }
        $fp = fopen($file, 'r');
        $lineNo = 1;
        $history = array();

        $pos = array('depth' => 1234);

        while ($lineNo <= $targetLineNo) {
            $line = fgets($fp);
            $lineInfo = $this->getLineInfo($line);
            if ($lineInfo) {
                $pos = [
                    'depth' => strlen($lineInfo[1]),
                    'line'  => $lineNo,
                    'call'  => $lineInfo[2],
                    'name'  => $lineInfo[3],
                ];
                for ($i = count($history) - 1; $i >= 0; $i--) {
                    if ($history[$i]['depth'] >= $pos['depth']) {
                        unset($history[$i]);
                    }
                }
                $history = array_values($history);
                $history[] = $pos;
            }
            $lineNo++;
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
                    $next = [
                        'depth' => $depth,
                        'line' => $lineNo,
                        'call' => $lineInfo[2],
                        'name'  => $lineInfo[3],
                    ];
                    break;
                }
            }
            $lineNo++;
        }
        fclose($fp);

        return [
            'breadcrumbs'   => $history,
            'next'          => $next,
        ];
    }

    /**
     * Get the trace context for the lines in the source.
     *
     * @param string $file The file to return the lines from.
     * @param integer $targetLineNo Number of the line to retrieve.
     * @param string $sourceFile The file to return the lines from.
     * @return array
     */
    protected function getSource($file, $targetLineNo, $sourceFile)
    {
        if (!is_file($file)) {
            return array();
        }
        $fp = fopen($file, 'r');
        $lineNo = 1;

        $context = array();
        while ($lineNo <= $targetLineNo) {
            $line = fgets($fp);
            if (strpos($line, $sourceFile)) {
                preg_match('/(?P<file>\/.*?:)(?P<line>\d+)/', $line, $matches);
                $context[$matches['line']] = $lineNo;
            }
            $lineNo++;
        }
        while (!feof($fp)) {
            $line = fgets($fp);
            if (strpos($line, $sourceFile)) {
                preg_match('/(?P<file>\/.*?:)(?P<line>\d+)/', $line, $matches);
                $context[$matches['line']] = $lineNo;
            }
            $lineNo++;
        }
        $this->totalLines = $lineNo - 1;
        fclose($fp);

        return $context;
    }

    /**
     * @param $line
     * @return bool
     */
    private function getLineInfo($line)
    {
        $lineRegExp = '/(\s+)->\s*([^\(]*?(\w+))\(/i';

        $lineInfo = false;
        if (preg_match($lineRegExp, $line, $matches)) {
            $lineInfo = $matches;
        }

        return $lineInfo;
    }

    /**
     * Extract information from a trace line
     *
     * @param string $line Line to process
     * @return array|bool
     */
    private function getLineDetails($line)
    {
        $regExp = '/(?P<time>\d+\.\d+)\s+(?P<memory>\d+)(?P<depth>\s+)->\s+(?P<call>.*)\s+(?P<path>[^\s]+?)(:(?P<line>\d+))?$/';
        $lineDetails = false;
        if (preg_match($regExp, $line, $matches)) {
            $lineDetails = [
                'depth'         => ceil(strlen($matches['depth']) / 2),
                'indent'        => $matches['depth'],
                'path_length'   => count(explode('/', $matches['path'])),
                'time'          => floatval($matches['time']),
                'memory'        => $matches['memory'],
                'call'          => $matches['call'],
                'file'          => $matches['path'],
                'line'          => $matches['line'],
            ];
        }

        return $lineDetails;
    }
}
