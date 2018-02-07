<?php
namespace DebugHelper\Gui;

use DebugHelper\Helper\Read;

class Delete
{
    const STEP_SEARCHING = 'searching';
    const STEP_DELETING = 'deleting';
    const STEP_FINISHED = 'finished';

    /**
     * @var string
     */
    protected $file;

    public function renderLoadsHtml()
    {
        if (empty($_GET['line'])) {
            throw new \Exception("Missing parameters line");

        }
        $toRemove = $_GET['line'] ;
        copy($this->file, $this->file . '.bak');
        $fileIn = fopen($this->file . '.bak', 'r');
        $fileOut = fopen($this->file, 'w');

        $regExp = '/(?P<line_no>\d+)\s+\d+\.\d+\s+\d+(?P<depth>\s+)->\s+.*\s+[^\s+]+:[\d+]+$/';
        $step = self::STEP_SEARCHING;
        $deletingFromDepth = 0;
        while (!feof($fileIn)) {
            $line = fgets($fileIn);
            if ($step !== self::STEP_FINISHED && preg_match($regExp, $line, $matches)) {
                $xtLine = isset($matches['line_no']) ? (int) $matches['line_no'] : 0;
                $depth  = ceil(strlen($matches['depth']) / 2);
                if ($step == self::STEP_SEARCHING && $xtLine == $toRemove) {
                    $step = self::STEP_DELETING;
                    $deletingFromDepth = $depth;
                } else if ($step == self::STEP_DELETING && $deletingFromDepth  >= $depth) {
                    $step = self::STEP_FINISHED;
                }
            }
            if ($step !== self::STEP_DELETING) {
                fwrite($fileOut, $line);
            }
        }
        fclose($fileIn);
        fclose($fileOut);
    }

    /**
     * Sets the value of file.
     *
     * @param string $fileId the file

     * @return $this
     * @throws \Exception
     */
    public function setFile($fileId)
    {
        $this->file = \DebugHelper::get('debug_dir') . $fileId. '.xt.clean';
        if (!is_file($this->file)) {
            throw new \Exception("Invalid file {$this->file}");
        }

        return $this;
    }
}
