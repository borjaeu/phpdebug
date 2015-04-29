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
       // if (!is_file($this->file . '.out')) {
            $processor  = new Processor();
            $processor->process($this->file);
      //  }
        return;
        $fp = fopen($this->file, 'r');
        $this->min_length = 65000;
        while (!feof($fp)) {
            $line = fgets($fp);
            $this->processLine($line);
        }
        return $this->buildHtml();

    }

    public function renderLoadsHtml()
    {
        $processor  = new Processor();
        $data = $processor->process($this->file);
        $this->renderPage($data);
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
        $file = \DebugHelper::getDebugDir() . $file . '.xt';

        if (!is_file($file)) {
            throw new \Exception("Error Processing file $file");
        }

        $this->file = $file;

        return $this;
    }


    protected function printFiles(array $files, $depth = 0)
    {
        $indent = str_repeat('  ', $depth);
        echo $indent . '<ul>';

        $item = <<<ITEM

$indent  <li class="%s">
$indent    <span>%06d&micro;s %s</span>
$indent    <div class="bar" style="width:%d%%"></div>

ITEM;
        foreach ($files as $file) {
            $has_children = count($file['children']);
            printf(
                $item,
                $has_children ? 'parent' : 'leaf',
                $file['time_children'],
                $file['call'],
                $file['relative'] * 100
            );
            if ($has_children) {
                $this->printFiles($file['children'], $depth + 2);
            }
            echo $indent . '  </li>';
        }

        echo $indent . '</ul>';
    }

    protected function renderPage($files)
    {
        echo <<<HTML
<!DOCTYPE HTML>
<html>
<head>
    <title>Report</title>
    <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/2.0.2/jquery.min.js"></script>
    <script type="text/javascript">
        $().ready(function(){
            $('li.parent > span').on('click', function() {
                console.log($(this).siblings('ul').slideToggle());
            });
        });
    </script>
    <style>
        * { font-family:courier,monospace; font-size:11px; }
        ul { list-style: none; padding-left 5px;}
        li { border-left: 1px solid black; border-bottom: 1px solid black;}
        li ul { display: none; }
        li.parent > span { cursor: pointer; }
        div.bar { border: 2px solid red;}
    </style>

</head>
<body>
HTML;
        $this->printFiles($files['children']);

        echo <<<HTML
</body>
</html>
HTML;
    }

}