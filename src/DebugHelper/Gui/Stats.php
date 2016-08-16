<?php
namespace DebugHelper\Gui;

class Stats
{
    protected $file;

    protected $min_length;

    protected $id;

    public function renderLoadsHtml()
    {
        $processor  = new Processor();
        $processor->process($this->id, false);

        $data = $processor->getTree();
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
        $this->file = \DebugHelper::get('debug_dir') . $file . '.xt.clean';

        if (!is_file($this->file)) {
            $this->file = \DebugHelper::get('debug_dir') . $file . '.xt';
            if (!is_file($this->file)) {
                throw new \Exception("Error Processing file $file");
            }
        }
        return $this;
    }

    protected function renderPage($files)
    {
        $template = new Template();
        $template->assign('id', $this->id);
        $template->assign('files', $files);
        $template->assign('section', 'stats');
        echo $template->fetch('stats');
    }
}
