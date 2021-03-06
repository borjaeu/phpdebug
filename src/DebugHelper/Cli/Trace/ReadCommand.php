<?php
namespace DebugHelper\Cli\Trace;

use DebugHelper\Helper\Read;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class ReadCommand
{
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
     * @var QuestionHelper
     */
    private $questionHelper;

    /**
     * @var array
     */
    private $history;

    /**
     * @param OutputInterface $output
     * @param InputInterface  $input
     * @param QuestionHelper  $questionHelper
     */
    public function __construct(OutputInterface $output, InputInterface $input, QuestionHelper $questionHelper)
    {
        $this->output = $output;
        $this->input = $input;
        /** @var QuestionHelper $helper */
        $this->questionHelper = $questionHelper;
    }

    /**
     * @param string $file
     */
    public function execute($file)
    {
        $this->history = [];

        $this->output = $output;
        $this->input = $input;
        $fileId = $this->getIdFromFile($file);
        $extension = 'xt';
        if (!is_file($this->getPathFromId($fileId, $extension))) {
            throw new \Exception("Error Processing file $fileId");
        }

        if (is_file($this->getPathFromId($fileId, 'xt.clean'))) {
            $extension = 'xt.clean';
        }
        $this->reader = new Read($this->getPathFromId($fileId, $extension));

        $this->fileSize = filesize($this->getPathFromId($fileId, $extension));
        $this->fileIn = fopen($this->getPathFromId($fileId, $extension), 'r');

        $output->write("Reading file {$fileId}.{$extension}.");
        $this->showLine(0);
    }

    /**
     * {@inheritdoc}
     */
    private function showLine($startLine)
    {
        $this->history[] = $startLine;
        if ($startLine) {
            $start = $startLine + 1;
            $depth = $this->reader->getDepth($startLine) + 1;
            $this->output->writeln("Depth $depth from $start");
        } else {
            $depth = $this->reader->getOuterDepth();
            $start = 0;
            $this->output->writeln("Outer depth $depth");
        }

        $lines = $this->reader->read($start, $depth);

        $maxWidth = 0;
        foreach ($lines as $line) {
            $maxWidth = max($maxWidth, strlen($line['call']) + 1);
        }
        $table = new Table($this->output);
        $table->setHeaders(['Line', 'Call', 'Child', 'Desc', 'Time', 'Passed', 'Spent', 'Path']);
        foreach ($lines as $index => $lineInfo) {
            $table->addRow([
                $lineInfo['xt_line'],
                $lineInfo['call'],
                $lineInfo['children'],
                $lineInfo['descendant'],
                (int) $lineInfo['time_acum'],
                (int) $lineInfo['time_spent'],
                (int) $lineInfo['mem_spent'],
                basename($lineInfo['path']) . ':' . $lineInfo['line'],
            ]);
        }
        $this->output->writeln(sprintf('%s', implode(' -> ', $this->history)));
        $table->render();
        $question = new Question('Select line: ');

        $line = $this->questionHelper->ask($this->input, $this->output, $question);

        if ($line) {
            $this->showLine($line);
        } else if (count($this->history) > 1) {
            array_pop($this->history);
            $line = array_pop($this->history);
            $this->showLine($line);
        }
    }
}
