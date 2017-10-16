<?php
namespace DebugHelper\Cli;

use DebugHelper\Helper\Read;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class ReadCommand extends Abstracted
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
        $this->setName('trace:read')
            ->setDescription('Read file configuration')
            ->addArgument('file', InputArgument::REQUIRED);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = $input->getArgument('file');
        $this->output = $output;
        $this->input = $input;

        $fileId = $this->getIdFromFile($file);

        if (!is_file($this->getPathFromId($fileId, 'xt'))) {
            throw new \Exception("Error Processing file $fileId");
        }

        if (!is_file($this->getPathFromId($fileId, 'xt.clean'))) {
            throw new \Exception("Invalid file {$fileId}.xt.clean");
        }
        $this->reader = new Read($this->getPathFromId($fileId, 'xt.clean'));

        $this->fileSize = filesize($this->getPathFromId($fileId, 'xt.clean'));
        $this->fileIn = fopen($this->getPathFromId($fileId, 'xt.clean'), 'r');

        $output->write("Reading file {$fileId}.xt.clean. ");

        $this->showLine(0);
    }

    /**
     * {@inheritdoc}
     */
    private function showLine($startLine)
    {
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

        $choices = [];
        foreach ($lines as $lineInfo) {
            $choices[] = $lineInfo['call'];
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
        $table->render();

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new Question('Select line: ');

        $line = $helper->ask($this->input, $this->output, $question);

        if ($line) {
            $this->showLine($line);
        }
    }
}
