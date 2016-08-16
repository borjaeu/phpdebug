<?php
namespace DebugHelper\Gui;

use DebugHelper\Helper\Read;

class Tree
{
    /**
     * @var string
     */
    protected $fileId;

    /**
     * @var Read
     */
    protected $fileReader;

    public function renderLoadsHtml()
    {
        if (empty($_GET['line'])) {
            $depth = $this->fileReader->getOuterDepth();
            $start = 0;
            $ajax = false;
        } else {
            $start = $_GET['line'] + 1;
            $depth = $this->fileReader->getDepth($_GET['line']) + 1;
            $ajax = true;
        }
        $lines = $this->fileReader->read($start, $depth);
        $lines = $this->calcExtraInfo($lines);

        $this->renderPage($lines, $ajax);
    }

    protected function calcExtraInfo($lines)
    {
        $totalTime = 0;
        foreach ($lines as $line) {
            $totalTime += $line['length'];
        }
        foreach ($lines as & $line) {
             $line['partial'] = $totalTime > 0 ? ceil(($line['length'] / $totalTime) * 100) : 0;
        }
        return $lines;
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
        $this->fileId = $fileId;
        $file = \DebugHelper::get('debug_dir') . $fileId. '.xt.clean';

        if (!is_file($file)) {
            throw new \Exception("Invalid file {$file}");
        }

        $this->fileReader = new Read($file);

        return $this;
    }

    protected function renderPage($lines, $ajax)
    {
        $template = new Template();
        $template->assign('id', $this->fileId);
        $template->assign('ajax', $ajax);
        $template->assign('lines', $lines);
        if ($ajax) {
            echo $template->fetch('tree_nodes');
        } else {
            echo $template->fetch('tree');
        }
    }
}
