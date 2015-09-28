<?php
namespace DebugHelper;

class Gui
{
    /**
     * Options sent to the GUI
     *
     * @var array
     */
    static protected $options;

    /**
     * @param array $options
     * @throws \Exception
     */
    public static function renderLoadsHtml(array $options = array())
    {
        self::$options = $options;

        if (isset($_GET['stats'])) {
            $stats = new \DebugHelper\Gui\Stats();
            $stats->setFile($_GET['stats'])->renderLoadsHtml();
        } elseif (isset($_GET['trace'])) {
            $trace = new \DebugHelper\Gui\Trace();
            $trace->setFile($_GET['trace'])->renderLoadsHtml();
        } elseif (isset($_GET['coverage'])) {
            $coverage = new \DebugHelper\Gui\Coverage();
            $coverage->setFile($_GET['coverage'])->renderLoadsHtml();
        } elseif (isset($_GET['diagram'])) {
            $coverage = new \DebugHelper\Gui\Diagram();
            $coverage->setFile($_GET['diagram'])->renderLoadsHtml();
        } elseif (isset($_GET['sequence'])) {
            $coverage = new \DebugHelper\Gui\Sequence();
            $coverage->setFile($_GET['sequence'])->renderLoadsHtml();
        } elseif (isset($_GET['res'])) {
            $resource = new \DebugHelper\Gui\Resource();
            $resource->setFile($_GET['res'])->renderLoadsHtml();
        } elseif (isset($_GET['delete']) && !self::get('readonly')) {
            self::delete($_GET['delete']);
            echo 'ok';
        } elseif (isset($_GET['rename']) && !self::get('readonly')) {
            self::rename($_GET['rename'], $_GET['name']);
            echo 'ok';
        } else {
            self::showIndex();
        }
    }

    /**
     * Gets and option if available or default value
     *
     * @param string $key Key for the option to get
     * @param mixed $default Default value for the option if not present
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        return isset(self::$options[$key]) ? self::$options[$key] : $default;
    }

    /**
     * Shows an index with all the available debug files
     */
    protected static function showIndex()
    {
        $files = self::getFiles();
        $template = new Gui\Template();
        $template->assign('root_dir', \DebugHelper::getDebugDir());
        $template->assign('files', $files);
        $template->assign('readonly', self::get('readonly', false));
        echo $template->fetch('index');
    }

    /**
     * Gets a list of available files with all its options
     *
     * @return array
     */
    protected static function getFiles()
    {
        $path = \DebugHelper::getDebugDir();

        $files = glob($path . '*.xt');
        array_walk($files, function (&$item) use ($path) {
            $time = self::getTraceTime($item);

            if (preg_match('/(?P<id>.*)\.xt$/', basename($item), $match)) {
                $info = json_decode(file_get_contents($path . $match['id'] . '.svr'), true);

                $item = array(
                    'id' => $match['id'],
                    'name' => $match['id'],
                    'time' => $time,
                    'path' => $item,
                    'details' => self::getDetails($info),
                    'info' => $info,
                    'size' => floor(filesize($item) / 1024),
                );
            }
        });
        usort($files, function ($itemA, $itemB) {
            if ($itemA['time'] == $itemB['time']) {
                return 0;
            }
            return $itemA['time'] > $itemB['time'] ? -1 : 1;
        });
        return $files;
    }

    /**
     * Gets the date of the debug from the trace file
     *
     * @param string $file Filename
     * @return mixed|string
     */
    protected static function getTraceTime($file)
    {
        $fp = fopen($file, 'r');
        $line = fgets($fp);
        fclose($fp);
        $line = preg_replace('/\s*TRACE START\s*\[(.*)\]/', '$1', $line);
        return $line;
    }

    /**
     * Loads details for the current debug file
     *
     * @param array $info Server information
     * @return string
     */
    protected static function getDetails($info)
    {
        $significantData = array('PHP_SELF', 'REMOTE_ADDR');
        $details = '';
        foreach ($significantData as $field) {
            if (isset($info['server'][$field])) {
                $details .= "$field: {$info['server'][$field]}\n";
            }
        }
        return trim($details);
    }

    /**
     * Delete debug file(s)
     *
     * @param string $id Identifier for the debug file
     */
    protected static function delete($id)
    {
        $path = \DebugHelper::getDebugDir();

        $files = glob($path . $id . '.*');
        array_walk($files, function ($item) {
            unlink($item);
        });
    }

    /**
     * Changes the file(s) name for a new one
     *
     * @param string $id Identifier for the debug file
     * @param string $name New name for the debug file
     */
    protected static function rename($id, $name)
    {
        $path = \DebugHelper::getDebugDir();

        $files = glob($path . $id . '.*');
        array_walk($files, function ($item) use ($name) {
            preg_match('/\.\w+$/', $item, $matches);
            rename($item, dirname($item) . '/' . $name . $matches[0]);
        });
    }
}
