<?php
namespace DebugHelper;

class Gui
{
    public static function renderLoadsHtml()
    {
        if (isset($_GET['stats'])) {
            $trace = new \DebugHelper\Gui\Stats();
            $trace->setFile($_GET['stats'])->renderLoadsHtml();
        } elseif (isset($_GET['trace'])) {
            $coverage = new \DebugHelper\Gui\Trace();
            $coverage->setFile($_GET['trace'])->renderLoadsHtml();
        } elseif (isset($_GET['delete'])) {
            self::delete($_GET['delete']);
            echo 'ok';
        } else {
            self::showIndex();
        }
    }

    protected static function showIndex()
    {
        $files = self::getFiles();
        $template = new Gui\Template();
        $template->assign('root_dir', \DebugHelper::getDebugDir());
        $template->assign('files', $files);
        echo $template->fetch('index');
    }

    protected static function getFiles()
    {
        $path = \DebugHelper::getDebugDir();

        $files = glob($path . '*.xt');
        array_walk($files, function (&$item) use ($path) {
            if (preg_match('/(?P<id>(\d{4})_(\d{2})_(\d{2})_(\d{2})_(\d{2})_(\d{2})_(\d+))\.xt$/', $item, $match)) {
                $info = json_decode(file_get_contents($path . $match['id'] . '.svr'), true);

                $item = array(
                    'id' => $match['id'],
                    'name' => "{$match[4]}/{$match[3]}/{$match[2]} {$match[5]}:{$match[6]}:{$match[7]} {$match[8]}",
                    'path' => $item,
                    'info' => $info,
                    'size' => floor(filesize($item) / 1024),
                );
            }
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
