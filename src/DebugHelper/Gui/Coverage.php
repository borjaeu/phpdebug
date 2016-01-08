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
        $template->assign('section', 'coverage');
        $template->assign('coverage', $coverage);
        echo $template->fetch('coverage_code');
    }

    protected function renderIndex($files)
    {
        $template = new Template();
        $template->assign('id', $this->id);
        $template->assign('files', $files);
        $tree = [];
        foreach($files as $file => $lines) {
            $levels = explode('/', $file);
            $this->insertBranches($levels, $tree, [
                'lines' => count($lines),
                'file'  => $file
            ]);
        }

        $tree = $this->collapseTree($tree);
        $template->assign('section', 'coverage');
        $template->assign('nodes', $tree);
        echo $template->fetch('coverage_index');
    }

    protected function collapseTree($tree, $parentKey = '')
    {
        if (isset($tree['file'])) {
            return $tree;
        }
        if(count($tree) == 1) {
            $key = array_keys($tree)[0];
            $newTree = $this->collapseTree($tree[$key], $parentKey . $key . '/');
        } else {
            $newTree = [];
            foreach($tree as $branch => $subtree) {
//                $newTree[$parentKey . $branch] = $subtree;
                $newTree[$parentKey . $branch] = $this->collapseTree($subtree);
            }
        }
        return $newTree;
    }


    protected function insertBranches($levels, &$tree, $value)
    {
        if (empty($levels)) {
            $tree = $value;
        } else {
            $level = array_shift($levels);
            if (!isset($tree[$level])) {
                $tree[$level] = [];
            }
            $this->insertBranches($levels, $tree[$level], $value);
        }
    }
}
