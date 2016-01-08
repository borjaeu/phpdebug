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

        $files = glob($path . '*.svr');
        array_walk($files, function (&$item) use ($path) {
            if (preg_match('/(?P<id>.*)\.svr$/', basename($item), $match)) {
                $hasTrace = is_file($path . $match['id'] . '.xt');
                $hasCoverage = is_file($path . $match['id'] . '.cvg');
                $hasCleanVersion = is_file($path . $match['id'] . '.xt.clean');
                $hasJsonStructure = is_file($path . $match['id'] . '.xt.json');

                $info = json_decode(file_get_contents($path . $match['id'] . '.svr'), true);
                $item = array(
                    'id'        => $match['id'],
                    'name'      => $match['id'],
                    'time'      => isset($info['time']) ? self::getRelativeTime($info['time']) : 0,
                    'path'      => $item,
                    'details'   => self::getDetails($info),
                    'trace'     => $hasTrace,
                    'coverage'  => $hasCoverage,
                    'clean'     => $hasCleanVersion,
                    'json'      => $hasJsonStructure,
                    'info'      => $info,
                    'size'      => $hasTrace ? self::fileSizeConvert(filesize($path . $match['id'] . '.xt')) : '-'

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

    protected static function getRelativeTime($timestamp)
    {
        if(!ctype_digit($timestamp)) {
            $timestamp = strtotime($timestamp);
        }

        $diff = time() - $timestamp;
        if($diff == 0) {
            return 'now';
        } elseif($diff > 0) {
            $day_diff = floor($diff / 86400);
            if($day_diff == 0) {
                if($diff < 60) return 'just now';
                if($diff < 120) return '1 minute ago';
                if($diff < 3600) return floor($diff / 60) . ' minutes ago';
                if($diff < 7200) return '1 hour ago';
                if($diff < 86400) return floor($diff / 3600) . ' hours ago';
            }
            if($day_diff == 1) return 'Yesterday';
            if($day_diff < 7) return $day_diff . ' days ago';
            if($day_diff < 31) return ceil($day_diff / 7) . ' weeks ago';
            if($day_diff < 60) return 'last month';
            return date('F Y', $timestamp);
        } else {
            $diff = abs($diff);
            $day_diff = floor($diff / 86400);
            if($day_diff == 0) {
                if($diff < 120) return 'in a minute';
                if($diff < 3600) return 'in ' . floor($diff / 60) . ' minutes';
                if($diff < 7200) return 'in an hour';
                if($diff < 86400) return 'in ' . floor($diff / 3600) . ' hours';
            }
            if($day_diff == 1) return 'Tomorrow';
            if($day_diff < 4) return date('l', $timestamp);
            if($day_diff < 7 + (7 - date('w'))) return 'next week';
            if(ceil($day_diff / 7) < 4) return 'in ' . ceil($day_diff / 7) . ' weeks';
            if(date('n', $timestamp) == date('n') + 1) return 'next month';
            return date('F Y', $timestamp);
        }
    }

    /**
     * Converts bytes into human readable file size.
     *
     * @param string $bytes
     * @return string human readable file size (2,87 Мб)
     * @author Mogilev Arseny
     */
    protected static function fileSizeConvert($bytes)
    {
        $bytes = floatval($bytes);
        $arBytes = array(
            0 => array("UNIT" => "TB", "VALUE" => pow(1024, 4)),
            1 => array("UNIT" => "GB", "VALUE" => pow(1024, 3)),
            2 => array("UNIT" => "MB", "VALUE" => pow(1024, 2)),
            3 => array("UNIT" => "KB", "VALUE" => 1024),
            4 => array("UNIT" => "B", "VALUE" => 1)
        );

        foreach($arBytes as $arItem) {
            if($bytes >= $arItem["VALUE"]) {
                $result = $bytes / $arItem["VALUE"];
                $result = str_replace(".", "," , strval(round($result, 2)))." ".$arItem["UNIT"];
                break;
            }
        }
        return $result;
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
