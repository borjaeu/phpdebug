<?php
namespace DebugHelper\Gui;

class Trace
{
    protected $file;

    protected $min_length;

    protected $id;

    public function renderLoadsHtml()
    {
        $processor  = new Processor();
        $data = $processor->process($this->file);
        $this->renderPage($data);
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

        $file = \DebugHelper::getDebugDir() . $file . '.xt';

        if (!is_file($file)) {
            throw new \Exception("Error Processing file $file");
        }

        $this->file = $file;

        return $this;
    }

    protected function renderPage($files)
    {
        $template = new Template();
        $template->assign('id', $this->id);
        $template->assign('files', $files);
        echo $template->fetch('trace');
    }
}
