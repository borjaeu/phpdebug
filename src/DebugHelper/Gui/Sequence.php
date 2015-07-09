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
        $template->assign('steps', count($steps));
        $template->assign('section', 'sequence');

        echo $template->fetch('sequence');
    }

    protected function getClasses(&$steps)
    {
        $namespaces = array();

        $positions = array();

        $position = 0;
        $classes = array();
        foreach ($steps as $index => $step) {
            $namespace = $this->getNamespace($step);

//            $namespace = $step['namespace'];
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

    protected function getNamespace(array $step)
    {
        static $namespaces = array();

        $path = preg_replace('/:\d+$/', '', $step['path']);

        if (!isset($namespaces[$path])) {
            $namespace = '';
            $class = false;
            $tokens = token_get_all(file_get_contents($path));
            for ($i = 0; $i<count($tokens); $i++) {
                if ($tokens[$i][0] === T_NAMESPACE) {
                    $namespace = '\\';
                    for ($j = $i+2; $j < count($tokens); $j++) {
                        if ($tokens[$j] == ';') {
                            break;
                        }
                        $namespace .= $tokens[$j][1];
                    }
                }
                if ($tokens[$i][0] === T_CLASS) {
                    $class = $tokens[$i+2][1];
                    break;
                }
            }
            if ($class) {
                $namespaces[$path] = $namespace . '\\' . $class;
            } else {
                $namespaces[$path] = basename($path);
            }
        }
        return $namespaces[$path];
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
