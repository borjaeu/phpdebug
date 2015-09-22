<?php
namespace DebugHelper\Gui;

class Resource
{
    protected $file;

    /**
     * Sets the value of file.
     *
     * @param string $file the file
     * @return self
     * @throws \Exception
     */
    public function setFile($file)
    {
        $this->file = __DIR__ . '/../../../res/' . $file;

        if (!is_file($this->file)) {
            header("HTTP/1.0 404 Not Found");
            exit;
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
        header('Content-type: application/javascript');
        echo file_get_contents($this->file);
    }
}
