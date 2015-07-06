<?php
namespace DebugHelper\Gui;

class Sequence
{
    protected $file;

    protected $id;

    public function renderLoadsHtml()
    {
        $processor  = new Processor();
        $processor->process($this->file);

        $steps = $processor->getLines();
        $classes = $this->getClasses($steps);
        $this->renderPage($classes, $steps);
    }

    protected function renderPage($classes, $steps)
    {
        $template = new Template();
        $template->assign('id', $this->id);

        $template->assign('classes', $classes);
        $template->assign('steps', $steps);
        $template->assign('section', 'sequence');

        echo $template->fetch('sequence');
    }

    protected function getClasses(&$steps)
    {
        $positions = array();

        $position = 0;
        $classes = array();
        foreach ($steps as $index => $step) {
            $namespace = $step['namespace'];
            if (!isset($classes[$namespace])) {
                $classes[$namespace] = array(
                    'pos' => ++$position,
                    'start' => $index,
                    'end' => $index + 1,
                    'length' => 1,
                    'steps' => array()
                );
            }
            $classes[$namespace]['end'] = $index + 1;
            $step['id'] = $index;
            $step['position'] = $index - $classes[$namespace]['start'];
            $last = isset($positions[$namespace]) ? $positions[$namespace] : $classes[$namespace]['start'] - 1;
            $step['margin'] = $index - $last - 1;
            $positions[$namespace] = $index;

            $classes[$namespace]['steps'][] = $step;
            $classes[$namespace]['length'] = $index - $classes[$namespace]['start'] + 1;
        }
        return $classes;
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
        $this->file = \DebugHelper::getDebugDir() . $file . '.xt';

        if (!is_file($this->file)) {
            throw new \Exception("Error Processing file $file");
        }
        return $this;
    }

    protected function getGenerateOutFile($file)
    {

    }
}
