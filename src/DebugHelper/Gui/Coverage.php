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
     * @param string $file File the file
     * @return $this
     * @throws \Exception
     */
    public function setFile($file)
    {
        $this->id = $file;
        $file = \DebugHelper::get('debug_dir') . $file . '.cvg';
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

    /**
     * Show the index of all coverage files
     *
     * @param array $files List of files in the coverage
     */
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
        $total = $this->buildStats($tree);
        $template->assign('section', 'coverage');
        $template->assign('nodes', $tree);
        $template->assign('total', $total);
        echo $template->fetch('coverage_index');
    }

    /**
     * Collapse the tree avoiding parent with only one child
     *
     * @param string $tree
     * @param string $parentKey
     * @return array
     */
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
                $newTree[$parentKey . $branch] = $this->collapseTree($subtree);
            }
        }
        return $newTree;
    }

    /**
     * Collapse the tree avoiding parent with only one child
     *
     * @param string $tree
     * @param string $parentKey
     * @return array
     */
    protected function buildStats(& $tree)
    {
        $total = 0;
        $children = [];
        foreach($tree as $key => $branch) {
            if (is_array($branch) && !isset($branch['file'])) {
                $total += $this->buildStats($branch);
            } else {
                $total++;
            }
            $children[$key] = $branch;
        }
        $tree = [
            'total'     => $total,
            'children'  => $children
        ];
        return $total;
    }

    /**
     * Recursive method to build the tree
     *
     * @param array $levels
     * @param array $tree Tree being built
     * @param $value
     */
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
