<?php
namespace DebugHelper\Cli;

use DebugHelper\Helper\Read;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class TimerCommand extends Abstracted
{
    /**
     * @var resource
     */
    private $fileIn;

    /**
     * @var integer
     */
    private $fileSize;

    /**
     * @var Read
     */
    private $reader;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('timer')
            ->setDescription('Get profile information from the timer');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->input = $input;

        $timerFile = $this->getTimerPath();

        if (!is_file($timerFile)) {
            throw new \InvalidArgumentException('The file ' . $timerFile . ' is not valid');
        }
        $stats = $this->getStats($timerFile);
        $this->runBasicReport($stats);
        $this->runTimeLineReport($stats);
    }

    /**
     * @param string $inputFile
     * @return array
     */
    private function getStats($inputFile)
    {
        $stats = [];
        $handler = fopen($inputFile, 'r');
        $index = -1;
        while(!feof($handler)) {
            $line = trim(fgets($handler));
            if (!preg_match('/(?<elapsed>\d+\.\d+)\s+(?<total>\d+\.\d+)\s+\[(?P<group>\w+)\]\s*(?P<message>.*)/', $line, $matches)) {
                $this->output->writeln('Invalid line ' . $line);
                continue;
            }
            $stats[] = [
                'group'   => $matches['group'],
                'total'   => (float) $matches['total'],
                'elapsed' => (float) $matches['elapsed'],
            ];
        }
        fclose($handler);

        return $stats;
    }

    /**
     * @param array $stats
     */
    private function runBasicReport(array $stats)
    {
        $grouped = [];
        foreach ($stats as $info) {
            $group = $info['group'];
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][] = $info['elapsed'];
        }
        $table = new Table($this->output);
        $table->setHeaders(['Group', 'Times', 'Min', 'Max', 'Average', 'Total']);
        $total = 0;
        foreach($grouped as $command => $values) {
            $table->addRow([
                    $command,
                    count($values),
                    min($values),
                    max($values),
                    array_sum($values) / count($values),
                    array_sum($values)]
            );
            $total += array_sum($values);
        }
        $table->render();
        $this->output->writeln(sprintf('Total <info>%s</info> time taken', $total));
        $headers = array_keys($grouped);
        $handler = fopen('report_basic.csv', 'w');
        fputcsv($handler, $headers, ';');
        $stillData = true;
        while($stillData) {
            $row = [];
            $stillData = false;
            foreach($headers as $header) {
                $row[] = array_shift($grouped[$header]);
                if (!empty($grouped[$header])) {
                    $stillData = true;
                }
            }
            fputcsv($handler, $row, ';');
        }
        fclose($handler);
    }

    /**
     * @param array $stats
     */
    private function runTimeLineReport(array $stats)
    {
        $headers = array_keys($stats[0]);
        $handler = fopen('report_time_line.csv', 'w');
        fputcsv($handler, $headers, ';');
        foreach ($stats as $command) {
            fputcsv($handler, $command, ';');
        }
        fclose($handler);
    }

    /**
     * @return string
     */
    private function getTimerPath()
    {
        $logPath = \DebugHelper::get('debug_dir');
        $logPath .= 'timer.log';

        return $logPath;
    }
}
