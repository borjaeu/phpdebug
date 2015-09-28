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
        $steps = json_decode(file_get_contents($this->file), true);
        $namespaces = $this->loadNamespaces($steps);

        $template = new Template();
        $template->assign('id', $this->id);

        $template->assign('file', realpath($this->file));
        $template->assign('section', 'sequence');
        $template->assign('resource', $this->getResourcePath());
        $template->assign('steps', json_encode($steps));
        $template->assign('namespaces', json_encode($namespaces));

        echo $template->fetch('sequence');
    }

    /**
     * Loads the namespaces from the steps
     *
     * @param array $steps
     */
    protected function loadNamespaces($steps)
    {
        $namespaces = ['root' => 0];
        foreach ($steps as $step) {
            if ($step['type'] == 1 || $step['type'] == 3) {
                $namespace = $step['namespace'];
                if (!isset($namespaces[$namespace])) {
                    $namespaces[$namespace] = count($namespaces);
                }
            }
        }
        return $namespaces;
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
