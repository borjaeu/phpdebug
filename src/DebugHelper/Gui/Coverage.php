<?php
namespace DebugHelper\Gui;

class Coverage
{
    protected $file;

    protected $id;

    public function renderLoadsHtml()
    {
        $files = json_decode(file_get_contents($this->file), true);
        if (isset($_GET['file'])) {
            $this->renderCode($_GET['file'], $files[$_GET['file']]);
        } else {
            $this->renderIndex($files);
        }

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
        $file = \DebugHelper::getDebugDir() . $file . '.cvg';
        if (!is_file($file)) {
            throw new \Exception("Error Processing file $file");
        }
        $this->file = $file;
        return $this;
    }

    protected function renderCode($file, $coverage)
    {
        $template = new Template();

        $code = file_get_contents($file);
        $lines = explode("\n", $code);

        $template->assign('id', $this->id);
        $template->assign('file', $file);
        $template->assign('lines', $lines);
        $template->assign('coverage', $coverage);
        echo $template->fetch('coverage_code');
    }

    protected function renderIndex($files)
    {
        $template = new Template();
        $template->assign('id', $this->id);
        $template->assign('files', $files);
        echo $template->fetch('coverage_index');
    }
}
