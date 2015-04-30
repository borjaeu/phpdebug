<?php
namespace DebugHelper;

class Gui
{
    public static function renderLoadsHtml()
    {
        $trace = new \DebugHelper\Gui\Trace();

        if (!empty($_GET['file'])) {
            $trace->setFile($_GET['file'])->renderLoadsHtml();
        } else {
            $files = $trace->getFiles();
            $html = '<ul>';
            foreach ($files as $file) {
                preg_match('/(\d{4})_(\d{2})_(\d{2})_(\d{2})_(\d{2})_(\d{2})/', $file, $match);
                $html .= "<li><a href=\"?file=$file\">{$match[3]}/{$match[2]}/{$match[1]}"
                    . "{$match[4]}:{$match[5]}:{$match[6]}</a>";
            }
            return $html;
        }
    }
}
