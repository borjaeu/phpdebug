<?php
namespace DebugHelper\Gui;

class Trace
{
    protected $file;

    protected $depth = 3;

    protected $lines = array();

    protected $min_length;

    public function loadHtml()
    {
        $fp = fopen($this->file, 'r');
        $this->min_length = 65000;
        while(!feof($fp)) {
            $line = fgets($fp);
            $this->processLine($line);
        }
        return $this->buildHtml();

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
        if (!is_file($file)) {
            throw new \Exception("Error Processing file $file");
        }

        $this->file = $file;

        return $this;
    }

    protected function processLine($line)
    {
        if (preg_match('/(?P<time>\d+\.\d+)\s+(?P<memory>\d+)(?P<depth>\s+)->\s+(?P<call>.*)\s+(?P<path>[^\s+]+)$/', $line, $matches)) {
            $matches = array_intersect_key($matches, array_flip(array_filter(array_keys($matches), 'is_string')));
            $matches['depth'] = strlen($matches['depth']);
            $this->min_length = min($this->min_length, $matches['depth']);
            $this->lines[] = $matches;
        }
    }

    protected function buildHtml()
    {
        $html = $this->getHeader();;
        foreach($this->lines as $line_data) {
            $html .= $this->buildLineHtml($line_data);
        }
        $html .= '</body></html>';
        return $html;
    }

    protected function buildLineHtml($line_data)
    {
        $filename = $this->getFilename($line_data['path']);
        $call = $this->processCall($line_data['call']);
        $depth = $line_data['depth'] - $this->min_length;
        $margin = str_repeat('-', $depth);

        return <<<LINE
<p onclick="window.location.href='codebrowser:{$line_data['path']}'" title="{$line_data['path']}">
    <span class="time">{$line_data['time']}</span>
    <span class="memory">{$line_data['memory']}</span>
    $margin
    <span class="call">{$call}</span>
    <span class="file">{$filename}</span>
</p>
LINE;
    }

    protected function processCall($call)
    {
        preg_match('/^(?P<class>.*(?P<call>->|::))?(?P<function>\w+)\((?<params>.*)\)/', $call, $matches);
        $matches = array_intersect_key($matches, array_flip(array_filter(array_keys($matches), 'is_string')));
        return <<<CALL
<span class="class">{$matches['class']}</span><span class="function">{$matches['function']}</span>(<span class="params">{$matches['params']}</span>)
CALL;
    }

    protected function getFilename($path)
    {
        $path = explode('/', $path);
        $path = array_slice($path, -$this->depth);
        return implode('/', $path);
    }

    protected function getHeader()
    {
        $header = <<<HEADER
<!DOCTYPE HTML>
<html>
<head>
<title>Trace file</title>
<style>
* { font-family:courier,monospace; font-size:13px; }
html, body { height:100%; }
p { margin:0; padding: 2px;}
    p:hover { background-color: #FF9 !important; cursor:pointer; }
.file { float:right;  text-align:right; }
p:nth-child(odd) { background-color: lightgray; }
.function { font-weight:bold; color:#008800; }
.class { color:#000088; }
.params{ color:#880088; }
</style>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/2.0.2/jquery.min.js"></script>
</head>
<body>
HEADER;
        return $header;
    }

}