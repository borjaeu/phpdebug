<?php
namespace DebugHelper\Cli;

use DebugHelper\Cli\Trace\CleanCommand;
use DebugHelper\Cli\Trace\ReadCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class ListCommand extends Abstracted
{
    /**
     * @var Table
     */
    private $table;

    /**
     * @var array
     */
    private $filesData;

    /**
     * @var bool
     */
    private $allClean;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var ReadCommand
     */
    private $readCommand;

    /**
     * @var CleanCommand
     */
    private $cleanCommand;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('trace')
            ->addArgument('config', InputArgument::OPTIONAL, 'Configuration file for cleaning', __DIR__ . '/../../../clean_config.yml')
            ->setDescription('Gets list of debug files');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->readCommand = new ReadCommand($output, $input, $this->getHelper('question'));
        $this->cleanCommand = new CleanCommand($output, $this->getConfigFile($input));
        $this->loadTable();
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new Question(': ');
        while(1) {
            $this->table->render();
            $this->output->write(sprintf('Choose file <info>1 - %d</info>', count($this->filesData)));
            if (!$this->allClean) {
                $this->output->write('; <info> c</info>lean files');
            }
            $selection = $helper->ask($input, $output, $question);
            if (is_numeric($selection)) {
                $this->showFile($selection);
            } elseif (strtolower($selection) === 'c') {
                $this->cleanFiles();
            } else {
                break;
            }
        }
    }

    /**
     * @param InputInterface $input
     * @return string
     */
    private function getConfigFile(InputInterface $input)
    {
        $configFileOption = $input->getArgument('config');
        if (!is_file($configFileOption)) {
            throw new \InvalidArgumentException('There is no configuration file ' . $configFileOption);
        }
        $this->output->writeln(sprintf('Using config <info>%s</info>', realpath($configFileOption)));

        return $configFileOption;
    }

    /**
     * @param int $selection
     */
    private function showFile($selection)
    {
        if (!isset($this->filesData[$selection])) {
            $this->output->writeln(sprintf('<error>Invalid file number #%d</error>', $selection));
        } elseif (!$this->filesData[$selection]['clean']) {
            $this->output->writeln(sprintf('<error>Invalid file number #%s</error>', $this->filesData[$selection]['name']));
        } else {
            $this->output->writeln(sprintf('<info>Reading file %s</info>', $this->filesData[$selection]['name']));
            $this->readCommand->execute($this->getPathFromId($this->filesData[$selection]['name'], 'xt.clean'));
        }
    }

    /**
     */
    private function cleanFiles()
    {
        if ($this->allClean) {
            $this->output->writeln(sprintf('<error>Files are already clean</error>'));
        } else {
            $files = [];
            foreach ($this->filesData as $index => $fileData) {
                if (!$fileData['clean']) {
                    $files[] = $this->getPathFromId($fileData['name'], 'xt');
                    $this->filesData[$index]['clean'] = true;
                }
            }
            $this->cleanCommand->execute($files);
            $this->loadTable();
        }
    }

    /**
     * Build output table
     */
    private function loadTable()
    {
        $this->loadFilesData();
        $this->table = new Table($this->output);
        $this->table->setHeaders(['#', 'name', 'time', 'start', 'end', 'elapsed', 'memory', 'size', 'clean']);
        $this->table->addRows($this->filesData);
    }

    /**
     * Gets the trace files from the temp directory
     */
    private function loadFilesData()
    {
        $path = \DebugHelper::get('debug_dir');
        $files = glob($path . '*.xt');
        $this->filesData = [];
        $index = 1;
        $this->allClean = true;
        foreach($files as $file) {
            $times = $this->getTraceStats($file);
            if (preg_match('/(?P<id>.*)\.xt$/', basename($file), $match)) {
                $this->allClean = $this->allClean && is_file($file . '.clean');
                $this->filesData[$index] = [
                    '#'       => $index,
                    'name'    => $match['id'],
                    'time'    => $times['time'],
                    'start'   => number_format($times['start'], 4),
                    'end'     => number_format($times['end'], 4),
                    'elapsed' => number_format($times['elapsed'], 4),
                    'memory'  => $times['memory'],
                    'size'    => floor(filesize($file) / 1024) . 'Kb',
                    'clean'   => is_file($file . '.clean'),
                ];
            }
            $index++;
        };
    }

    /**
     * Gets the trace time from the file contents
     *
     * @param string $file
     * @return array
     */
    private function getTraceStats($file)
    {
        $fp = fopen($file, 'r');
        $startLine = fgets($fp);
        $firstLine = fgets($fp);
        $lastLine = '';
        while (!feof($fp)) {
            $line = fgets($fp);
            if (substr($line, 0, 9) == 'TRACE END') {
                break;
            }
            $lastLine = $line;
        }
        $startTime = preg_replace('/\s*TRACE START\s*\[(.*)\]/', '$1', $startLine);
        $firstTimestamp = (float) preg_replace('/\s*(\d+\.\d+).*/', '$1', $firstLine);
        $firstMemory = (int) preg_replace('/\s*\d+\.\d+\s+(\d+).*/', '$1', $firstLine);
        $lastTimestamp = (float) preg_replace('/\s*(\d+\.\d+).*/', '$1', $lastLine);
        $lastMemory = (int) preg_replace('/\s*\d+\.\d+\s+(\d+).*/', '$1', $lastLine);
        fclose($fp);
        return [
            'time'    => trim($startTime),
            'start'   => $firstTimestamp,
            'end'     => $lastTimestamp,
            'memory'  => $lastMemory - $firstMemory,
            'elapsed' => $lastTimestamp - $firstTimestamp,
        ];
    }
}
