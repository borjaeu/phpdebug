<?php
namespace DebugHelper\Cli\Util;

class Graphviz
{
    /**
     * @var array
     */
    protected $nodes;

    /**
     * @var array
     */
    protected $edges;

    /**
     * @var int
     */
    protected $index;
    /**
     * @var bool
     */
    protected $debug;

    /**
     * Graphviz constructor.
     */
    public function __construct()
    {
        $this->nodes = [];
        $this->edges = [];
        $this->index = 0;
        $this->debug = false;
    }

    /**
     * @param boolean $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

    /**
     * @param string $name
     * @param array $options
     */
    public function addNode($name, array $options = [])
    {
        $this->loadNode($name, $options);
    }

    /**
     * @param $name
     */
    public function removeNode($name)
    {
        $nodeId = $this->loadNode($name, []);
        unset($this->nodes[$name]);

        foreach ($this->edges as $id => $edge) {
            if ($edge['source'] == $nodeId || $edge['target'] == $nodeId) {
                unset($this->edges[$id]);
            }
        }
    }

    /**
     * @param string $source
     * @param string $target
     * @param array $options
     * @return string
     */
    public function addEdge($source, $target, array $options = [])
    {
        if ($source == $target) {
            return '';
        }

        $id = md5($source . '-' . $target);

        $sourceId = $this->loadNode($source, []);
        $targetId = $this->loadNode($target, []);
        if (!isset($this->edges[$id])) {
            $this->edges[$id] = [
                'source' => $sourceId,
                'target' => $targetId,
                'options' => $options,
            ];
            $this->nodes[$source]['uses']++;
            $this->nodes[$target]['uses']++;
        }

        return $id;
    }

    /**
     * @param string $name
     * @param array $options
     * @return string
     */
    private function loadNode($name, array $options)
    {
        if (!isset($this->nodes[$name])) {
            $options['label'] = $name;
            $this->index++;
            $this->nodes[$name] = [
                'id' => 'Node' . $this->index,
                'options' => $options,
                'uses' => 0,
            ];
        }

        return $this->nodes[$name]['id'];
    }

    public function getEmptyNodes()
    {
        $nodes = [];

        foreach ($this->nodes as $node => $nodeInfo) {
            if ($nodeInfo['uses'] == 0) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * @return string
     */
    public function getDotCode()
    {
        $graph = <<<HEREDOC
digraph {

HEREDOC;
        foreach ($this->nodes as $label => $node) {
            $node['options']['label'] .= ' x' . $node['uses'];
            $options = $this->buildOptions($node['options']);
            $graph .= "   {$node['id']} $options;\n";
        }
        foreach ($this->edges as $id => $edge) {
            if ($this->debug) {
                $edge['options']['label'] = $id;
            }
            $options = $this->buildOptions($edge['options']);
            $graph .= "   {$edge['source']} -> {$edge['target']} $options;\n";
        }
        $graph .= <<<HEREDOC
}
HEREDOC;

        return $graph;
    }

    /**
     * @return int
     */
    public function getNodesCount()
    {
        return count($this->nodes);
    }

    /**
     * @return int
     */
    public function getEdgesCount()
    {
        return count($this->edges);
    }

    private function buildOptions(array $options)
    {
        $optionsLabels = '';
        if (!empty($options)) {
            $optionsLabels = "[";
            foreach ($options as $option => $value) {
                $optionsLabels .= sprintf('%s = "%s" ', $option, $value);
            }
            $optionsLabels .= "]";
        }

        return $optionsLabels;
    }
}
