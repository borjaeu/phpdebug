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
        $table->setHeaders(['id', 'name', 'time', 'size']);

        foreach ($files as $info) {
            $table->addRow([$info['id'], $info['name'], $info['time'], $info['size']]);
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
