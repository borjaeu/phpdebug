<?php
namespace DebugHelper\Cli;

class ListCommand extends Abstracted
{
    /**
     * Execute the command line
     */
    public function run()
    {
        $files = $this->getFiles();
        foreach ($files as $info) {
            printf('%30s %30s %21s %10s %s', $info['id'], $info['name'], $info['time'], $info['size'], PHP_EOL);
        }
    }

    /**
     * Gets the trace fiels from the temp directory
     *
     * @return array
     */
    protected function getFiles()
    {
        $path = \DebugHelper::getDebugDir();

        $files = glob($path . '*.xt');
        array_walk($files, function (&$item) use ($path) {
            $time = self::getTraceTime($item);

            if (preg_match('/(?P<id>.*)\.xt$/', basename($item), $match)) {
                $item = array(
                    'id' => $match['id'],
                    'name' => $match['id'],
                    'time' => $time,
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
     * Gets the trace time from the file contents
     *
     * @param string $file
     * @return string
     */
    protected function getTraceTime($file)
    {
        $fp = fopen($file, 'r');
        $line = fgets($fp);
        fclose($fp);
        $line = preg_replace('/\s*TRACE START\s*\[(.*)\]/', '$1', $line);
        return trim($line);
    }
}
