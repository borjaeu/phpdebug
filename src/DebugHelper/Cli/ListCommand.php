<?php
namespace DebugHelper\Cli;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends Abstracted
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('trace:list')
            ->setDescription('Gets list of debug files');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $files = $this->getFiles();

        $table = new Table($output);
        $table->setHeaders(['id', 'name', 'time', 'start', 'end', 'elapsed', 'size']);

        foreach ($files as $info) {
            $table->addRow([$info['id'], $info['name'], $info['time'], $info['start'], $info['end'], $info['elapsed'], $info['size']]);
        }

        $table->render();
    }

    /**
     * Gets the trace fiels from the temp directory
     *
     * @return array
     */
    protected function getFiles()
    {
        $path = \DebugHelper::get('debug_dir');

        $files = glob($path . '*.xt');
        array_walk($files, function (&$item) use ($path) {
            $times = self::getTraceTime($item);



            if (preg_match('/(?P<id>.*)\.xt$/', basename($item), $match)) {
                $item = [
                    'id' => $match['id'],
                    'name' => $match['id'],
                    'time' => $times['time'],
                    'start' => $times['start'],
                    'end' => $times['end'],
                    'elapsed' => $times['elapsed'],
                    'size' => floor(filesize($item) / 1024),
                ];
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
        $startLine = fgets($fp);
        $firstLine = fgets($fp);
        while (!feof($fp)) {
            $line = fgets($fp);
            if (substr($line, 0, 9) == 'TRACE END') {
                break;
            }
            $lastLine = $line;
        }
        $startTime = preg_replace('/\s*TRACE START\s*\[(.*)\]/', '$1', $startLine);
        $start = (float) preg_replace('/\s*(\d+\.\d+).*/', '$1', $firstLine);
        $end = (float) preg_replace('/\s*(\d+\.\d+).*/', '$1', $lastLine);
        fclose($fp);
        return [
            'time'  => trim($startTime),
            'start' => $start,
            'end'   => $end,
            'elapsed' => $end - $start,
        ];
    }
}
