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
        try {
            $this->reader = new Read($file);
        } catch (\Exception $e) {
            return;
        };
        $this->fileSize = filesize($file);
        $this->fileIn = fopen($file, 'r');

        $this->showLine(0);
    }

    /**
     * {@inheritdoc}
     */
    private function showLine($startLine)
    {
        $this->history[] = $startLine;
        $domain = '_qwertyuiopasdfghjklzxcvbnm';
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
        $table->setHeaders(['Line', 'Call', 'Child', 'Desc', 'Time', 'Spent', 'Path']);

        foreach ($lines as $index => $lineInfo) {
            $table->addRow([
                $lineInfo['line'],
                $lineInfo['call'],
                $lineInfo['children'],
                $lineInfo['descendant'],
                (int) $lineInfo['time_acum'],
                (int) $lineInfo['time_spent'],
                basename($lineInfo['path']),
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
