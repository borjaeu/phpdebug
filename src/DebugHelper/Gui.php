<?php
namespace DebugHelper;

class Gui
{
    public static function renderLoadsHtml()
    {
        if (isset($_GET['trace'])) {
            $trace = new \DebugHelper\Gui\Trace();
            $trace->setFile($_GET['trace'])->renderLoadsHtml();
        } elseif (isset($_GET['code'])) {
            $coverage = new \DebugHelper\Gui\Coverage();
            $coverage->setFile($_GET['code'])->renderLoadsHtml();
        } elseif (isset($_GET['delete'])) {
            self::delete($_GET['delete']);
            self::showIndex();
        } else {
            self::showIndex();
        }
    }

    protected static function showIndex()
    {
        $files = self::getFiles();
        $template = new Gui\Template();
        $template->assign('files', $files);
        echo $template->fetch('index');
    }

    protected static function getFiles()
    {
        $path = \DebugHelper::getDebugDir();

        $files = glob($path . '*.xt');
        array_walk($files, function (&$item) {
            preg_match('/(?P<id>(\d{4})_(\d{2})_(\d{2})_(\d{2})_(\d{2})_(\d{2}))\.xt$/', $item, $match);
            $item = array(
                'id' => $match['id'],
                'name' => "{$match[4]}/{$match[3]}/{$match[2]} {$match[5]}:{$match[6]}:{$match[7]}",
                'path' => $item
            );
        });
        return $files;
    }

    protected static function delete($id)
    {
        $path = \DebugHelper::getDebugDir();

        $files = glob($path . $id . '.*');
        array_walk($files, function ($item) {
            unlink($item);
        });
    }
}
