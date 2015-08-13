<?php
namespace DebugHelper\Gui;

class Files
{
    protected $id;
    protected $coverage_file;
    protected $trace_file;
    protected $coverage;

    public function renderLoadsHtml()
    {
        $this->renderFiles();
    }

    protected function renderFiles()
    {
        $template = new Template();
        $template->assign('id', $this->id);
        $template->assign('files', $this->getFiles('/tmp'));
        echo $template->fetch('files');
    }

    protected function getFiles($dir)
    {
        chdir($dir);
        $files = shell_exec('ls -la');
        print_r($files);
        exit;
    }
}
