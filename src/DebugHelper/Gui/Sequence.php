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
        $this->file = \DebugHelper::getDebugDir() . $file . '.json';

        if (!is_file($this->file)) {
            throw new \Exception("Error Processing file $file");
        }
        return $this;
    }

    /**
     * Renders teh html for the sequence diagram
     *
     * @throws \Exception
     */
    public function renderLoadsHtml()
    {
        $data = json_decode(file_get_contents($this->file), true);

        $template = new Template();
        $template->assign('id', $this->id);

        $template->assign('file', realpath($this->file));
        $template->assign('section', 'sequence');
        $template->assign('resource', $this->getResourcePath());
        $template->assign('steps', json_encode($data['steps']));
        $template->assign('namespaces', json_encode($data['namespaces']));

        echo $template->fetch('sequence');
    }

    /**
     * Gets the roots path to load resources
     *
     * @return string
     */
    protected function getResourcePath()
    {
          return preg_replace('/\?.*$/', '?res=', $_SERVER['REQUEST_URI']);
    }
}
