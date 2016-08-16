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
    public function setFile($fileId)
    {
        $this->id = $fileId;
        $this->file = \DebugHelper::get('debug_dir') . $fileId . '.xt.diag.json';


        if (!is_file($this->file)) {
            throw new \Exception("Error Processing file $fileId {$this->file}");
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
        if (empty($_GET['operation'])) {
            $namespaces = $this->loadNamespaces($steps);

            $template = new Template();
            $template->assign('id', $this->id);
            $template->assign('file', realpath($this->file));
            $template->assign('section', 'sequence');
            $template->assign('resource', $this->getResourcePath());
            $template->assign('steps', json_encode($steps));
            $template->assign('namespaces', json_encode($namespaces));

            echo $template->fetch('sequence');
        } else {
            switch($_GET['operation']) {
                case 'delete':
                    $steps = $this->remove($steps, $_GET['step']);
            }
            file_put_contents($this->file, json_encode($steps, JSON_PRETTY_PRINT));
            $namespaces = $this->loadNamespaces($steps);

            echo json_encode(['steps' => $steps, 'namespaces' => $namespaces]);
        }
    }

    /**
     * Remove an element from the step
     *
     * @param array $steps Steps to remove the information from
     * @param string $key Key to remove from the steps
     * @return array
     */
    protected function remove($steps, $key)
    {
        if (isset($steps[$key])) {
            if ($steps[$key]['type'] == 3) {
                unset($steps[$key]);
            } else {
                $removing = false;
                $stepType = $steps[$key]['type'];
                foreach ($steps as $currentKey => $step) {
                    $matchesFrom = (isset($step['from']) && $step['from'] == $key);
                    $matchesEnd = (isset($step['end']) && $step['end'] == $key);
                    if (($stepType == 2 && $currentKey == $key) || $matchesFrom) {
                        unset($steps[$currentKey]);
                        break;
                    } elseif (($stepType == 1 && $currentKey == $key) || $matchesEnd) {
                        unset($steps[$currentKey]);
                        $removing = true;
                    } elseif ($removing) {
                        unset($steps[$currentKey]);
                    }
                }
            }
        }
        return $steps;
    }

    /**
     * Loads the namespaces from the steps
     *
     * @param array $steps
     */
    protected function loadNamespaces($steps)
    {
        $namespaces = [current($steps)['source'] => 0];

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
          return preg_replace('/\?.*$/', '?kizilare_debug=1&res=', $_SERVER['REQUEST_URI']);
    }
}
