<?php
namespace DebugHelper\Gui;

class Sequence
{
    protected $file;

    protected $id;

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
        $this->file = \DebugHelper::getDebugDir() . $file . '.xt';

        if (!is_file($this->file)) {
            throw new \Exception("Error Processing file $file");
        }
        return $this;
    }

    public function renderLoadsHtml()
    {
        if (!is_file($this->file . '.json')) {
            throw new \Exception('Invalid file ' . $this->file . '.json');
        }
        $data = json_decode(file_get_contents($this->file . '.json'), true);

        $template = new Template();
        $template->assign('id', $this->id);

        $template->assign('section', 'sequence');
        $template->assign('steps', json_encode($data['steps']));
        $template->assign('namespaces', json_encode($data['namespaces']));

        echo $template->fetch('sequence');
    }
}

