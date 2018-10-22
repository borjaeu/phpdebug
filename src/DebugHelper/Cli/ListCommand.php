<?php
namespace DebugHelper\Cli;

use DebugHelper\Cli\Trace\CleanCommand;
use DebugHelper\Cli\Trace\CompareCoverageCommand;
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
     * @var CompareCoverageCommand
     */
    private $compareCoverageCommand;

    /**
     * @var array
     */
    private $fileExtensions = ['svr', 'cvg', 'xt', 'xt.clean'];

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('files')
            ->addArgument('config', InputArgument::OPTIONAL, 'Configuration file for cleaning', __DIR__ . '/../../../clean_config.json')
            ->setDescription('Gets list of debug files');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output                 = $output;
        $this->readCommand            = new ReadCommand($output, $input, $this->getHelper('question'));
        $this->cleanCommand           = new CleanCommand($output, $this->getConfigFile($input));
        $this->compareCoverageCommand = new CompareCoverageCommand($output, $this->getConfigFile($input));
        $this->loadTable();
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new Question(': ');
        $autocompleterValues = ['browse', 'compare', 'name', 'delete', 'refresh', 'process'];
        $question->setAutocompleterValues($autocompleterValues);
        while(1) {
            $this->table->render();
            $this->output->write(implode(', ', $autocompleterValues));
            $selection = $helper->ask($input, $output, $question);
            if (!preg_match('/^(?P<option>\w+)(\s+(?P<source>\d+)(\s+(?P<target>\d+))?)?/', $selection, $matches)) {
                break;
            }
            switch (strtolower($matches['option'])) {
                case 'browse':
                    $this->showFile($matches['source']);
                    break;
                case 'name':
                    $this->nameFile($matches['source'], $input);
                    break;
                case 'process':
                    $this->cleanFiles();
                    break;
                case 'compare':
                    $this->compareCoverage($matches['source'], $matches['target']);
                    break;
                case 'delete':
                    $this->deleteFiles($matches['source']);
                    break;
                case 'refresh':
                    $this->loadTable();
                    break;
                default:
                    $output->writeln(sprintf('<error>%s</error> Invalid option', $matches['option']));
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
     * @param int $source
     */
    private function showFile($source)
    {
        if (!isset($this->filesData[$source])) {
            $this->output->writeln(sprintf('<error>Invalid file number #%d</error>', $source));
        } elseif (!is_file($this->getPathFromId($this->filesData[$source]['name'], 'xt.clean'))) {
            $this->output->writeln(sprintf('<error>There is not clean file for #%d</error>', $source, $this->filesData[$source]['name']));
        } else {
            $this->output->writeln(sprintf('<info>Reading file %s</info>', $this->filesData[$source]['name']));
            $this->readCommand->execute($this->getPathFromId($this->filesData[$source]['name'], 'xt.clean'));
        }
    }

    /**
     * @param int            $source
     * @param InputInterface $input
     */
    private function nameFile($source, InputInterface $input)
    {
        if (!isset($this->filesData[$source])) {
            $this->output->writeln(sprintf('<error>Invalid file number #%d</error>', $source));

            return;
        }
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new Question(sprintf('Name "%s" as: ', $this->filesData[$source]['name']));
        $newName = $helper->ask($input, $this->output, $question);
        $renamed = [];
        foreach ($this->fileExtensions as $extension) {
            $fileToRename = $this->getPathFromId($this->filesData[$source]['name'], $extension);
            if (is_file($fileToRename)) {
                $newFilename = $this->getPathFromId($newName, $extension);
                rename($fileToRename, $newFilename);
                $renamed[] = basename($fileToRename) . ' to ' . basename($newFilename);
            }
        }
        if ($renamed) {
            $this->output->writeln(sprintf('Renamed files: %s', implode(', ', $renamed)));
            $this->loadTable();
        } else {
            $this->output->writeln('No files to rename');
        }
    }

    /**
     * @param int $source
     * @param int $target
     */
    private function compareCoverage($source, $target)
    {
        if (!isset($this->filesData[$source])) {
            $this->output->writeln(sprintf('<error>Invalid file number #%d</error>', $source));
        } else if (!isset($this->filesData[$target])) {
            $this->output->writeln(sprintf('<error>Invalid file number #%d</error>', $target));
        } elseif (!is_file($this->getPathFromId($this->filesData[$source]['name'], 'cvg'))) {
            $this->output->writeln(sprintf('<error>There is not coverage file for #%d</error>', $source, $this->filesData[$source]['name']));
        } elseif (!is_file($this->getPathFromId($this->filesData[$target]['name'], 'cvg'))) {
            $this->output->writeln(sprintf('<error>There is not coverage file for #%d</error>', $target, $this->filesData[$target]['name']));
        } else {
            $this->compareCoverageCommand->execute(
                $this->getPathFromId($this->filesData[$source]['name'], 'cvg'),
                $this->getPathFromId($this->filesData[$target]['name'], 'cvg')
            );
        }
    }

    /**
     * @param int $source
     */
    private function deleteFiles($source)
    {
        if (!isset($this->filesData[$source])) {
            $this->output->writeln(sprintf('<error>Invalid file number #%d</error>', $source));
        } else {
            $deleted = [];
            foreach ($this->fileExtensions as $extension) {
                $fileToDelete = $this->getPathFromId($this->filesData[$source]['name'], $extension);
                if (is_file($fileToDelete)) {
                    unlink($fileToDelete);
                    $deleted[] = basename($fileToDelete);
                }
            }
            if ($deleted) {
                $this->output->writeln(sprintf('Deleted: %s', implode(', ', $deleted)));
                $this->loadTable();
            } else {
                $this->output->writeln('No files to delete');
            }
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
                if (is_file($this->getPathFromId($fileData['name'], 'xt')) && !is_file($this->getPathFromId($fileData['name'], 'xt.clean'))) {
                    $files[] = $this->getPathFromId($fileData['name'], 'xt');
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
        $this->table->setHeaders(['#', 'Name', 'Trace', 'Coverage', 'Time', 'Start', 'End', 'Elapsed', 'Memory']);
        $this->table->addRows($this->filesData);
    }

    /**
     * Gets the trace files from the temp directory
     */
    private function loadFilesData()
    {
        $path = \DebugHelper::get('debug_dir');
        $files = glob($path . '*');
        $this->filesData = [];
        $index = 1;
        $this->allClean = true;
        $processed = [];
        foreach($files as $file) {
            if (!preg_match('/(?P<id>.*)\.(xt|cvg)$/', basename($file), $match)) {
                continue;
            }
            if (in_array($match['id'], $processed)) {
                continue;
            }
            $processed[] = $match['id'];
            $traceStats = [
                'time'    => '-',
                'start'   => '-',
                'end'     => '-',
                'memory'  => '-',
                'elapsed' => '-',
                'size'    => '-',
            ];
            $this->allClean = $this->allClean && is_file($file . '.clean');

            $hasTrace = 'No';
            if (is_file($path . $match['id'] . '.xt')) {
                $hasTrace = 'Yes ' . number_format(floor(filesize($file) / 1024)) . 'Kb';
                $traceStats = $this->getTraceStats($path . $match['id'] . '.xt');
            }
            $hasCoverage = 'No';
            if (is_file($path . $match['id'] . '.cvg')) {
                $hasCoverage = 'Yes ' . number_format(floor(filesize($file) / 1024)) . 'Kb';
            }
            if (is_file($path . $match['id'] . '.clean')) {
                $hasTrace = 'Clean';
            }
            $this->filesData[$index] = [
                '#'        => $index,
                'name'     => $match['id'],
                'trace'    => $hasTrace,
                'coverage' => $hasCoverage,
                'time'     => $traceStats['time'],
                'start'    => $traceStats['start'],
                'end'      => $traceStats['end'],
                'elapsed'  => $traceStats['elapsed'],
                'memory'   => $traceStats['memory'],
            ];
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
            'start'   => number_format($firstTimestamp, 4),
            'end'     => number_format($lastTimestamp, 4),
            'elapsed' => number_format($lastTimestamp - $firstTimestamp, 4),
            'memory'  => $lastMemory - $firstMemory,
            'size'    => number_format(floor(filesize($file) / 1024)) . 'Kb',
        ];
    }
}
