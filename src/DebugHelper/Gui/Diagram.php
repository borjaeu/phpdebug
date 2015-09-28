<?php
namespace DebugHelper\Gui;

class Diagram
{
    protected $file;

    protected $id;

    protected $classes;

    protected $position;

    public function renderLoadsHtml()
    {
        $processor  = new Processor();
        $processor->process($this->file);

        $steps = $processor->getLines();
        $this->loadClasses($steps);
        $this->renderPage($this->classes, $steps);
    }

    protected function renderPage($classes, $steps)
    {
        $template = new Template();
        $template->assign('id', $this->id);

        $template->assign('classes', $classes);
        $template->assign('steps', count($steps));
        $template->assign('section', 'diagram');

        echo $template->fetch('diagram');
    }

    protected function loadClasses(&$steps)
    {
        $this->classes = array();
        $positions = array();

        $this->position = 0;

        foreach ($steps as $index => $step) {
            $namespace_out = $this->getNamespace($step);
            $namespace_in = '\\' . $step['namespace'];

            $this->loadNamespace($namespace_out, $index);
            $this->loadNamespace($namespace_in, $index);

            $this->classes[$namespace_out]['end'] = $index + 1;
            $this->classes[$namespace_in]['end'] = $index + 1;

            $step = $this->getSimplifiedStep($step);

            $step['index'] = $index;
            $step['id'] = "{$index}_out";
            $step['type'] = 'out';
            $step['position'] = $index - $this->classes[$namespace_out]['start'];
            $last = isset($positions[$namespace_out])
                ? $positions[$namespace_out]
                : $this->classes[$namespace_out]['start'] - 1;
            $step['margin'] = $index - $last - 1;
            $positions[$namespace_out] = $index;

            $this->classes[$namespace_out]['steps'][] = $step;
            $this->classes[$namespace_out]['length'] = $index - $this->classes[$namespace_out]['start'] + 1;

            $step['id'] = "{$index}_in";
            $step['type'] = 'in';
            $step['position'] = $index - $this->classes[$namespace_in]['start'];
            $last = isset($positions[$namespace_in])
                ? $positions[$namespace_in]
                : $this->classes[$namespace_in]['start'] - 1;
            $step['margin'] = $index - $last - 1;
            $positions[$namespace_in] = $index;

            $this->classes[$namespace_in]['steps'][] = $step;
            $this->classes[$namespace_in]['length'] = $index - $this->classes[$namespace_in]['start'] + 1;
        }
    }


    protected function loadNamespace($namespace, $index)
    {
        if (!isset($this->classes[$namespace])) {
            $this->classes[$namespace] = array(
                'pos'       => ++$this->position,
                'start'     => $index,
                'end'       => $index + 1,
                'length'    => 1,
                'steps'     => array()
            );
        }
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

    protected function getSimplifiedStep($step)
    {
        return array(
            'namespace' => $step['namespace'],
            'method'    => $step['method'],
            'call'      => $step['call'],
            'path'      => $step['path'],
            'line_no'   => $step['line_no'],
        );
    }

    protected function getGenerateOutFile($file)
    {

    }
}
