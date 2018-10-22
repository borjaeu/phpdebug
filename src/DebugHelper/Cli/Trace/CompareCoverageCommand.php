<?php
namespace DebugHelper\Cli\Trace;

use DebugHelper\Helper\Read;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class CompareCoverageCommand
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
     * @param OutputInterface $output
     * @param InputInterface  $input
     * @param QuestionHelper  $questionHelper
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @param string $file
     */
    public function execute($source, $target)
    {
        $sourceData = json_decode(file_get_contents($source), true);
        $targetData = json_decode(file_get_contents($target), true);
        $diff = $this->arrayDiff($sourceData, $targetData);
    }

    /**
     * Returns a definition of changes.
     *
     * @param array $before First array
     * @param array $after Second array
     * @return array
     */
    private function arrayDiff($source, $target)
    {
        $comparisonResult = [];
        foreach ($source as $file => $lines) {
            if (isset($target[$file])) {
                $comparison = $this->compareLines($source[$file], $target[$file]);
                if ($comparison) {
                    $comparisonResult[$file] = $comparison;
                }
                unset($target[$file]);
            } else {
                $comparisonResult[$file] = 'source';
            }
        }
        foreach ($target as $file => $lines) {
            $comparisonResult[$file] = 'target';
        }
        ksort($comparisonResult);
        foreach ($comparisonResult as $file => $location) {
            if (is_array($location)) {
                $this->output->writeln(sprintf('%s:', $file));
                foreach ($location as $line => $lineLocation) {
                    $this->output->writeln(sprintf(' - %d <info>%s</info>', $line, $lineLocation));
                }
            } else {
                $this->output->writeln(sprintf('%s <info>%s</info>', $file, $location));
            }
        }

        return $comparisonResult;
    }

    private function compareLines($sourceLines, $targetLines)
    {
        $comparisonResult = [];
        foreach ($sourceLines as $line => $count) {
            if (isset($targetLines[$line])) {
                unset($targetLines[$line]);
            } else {
                $comparisonResult[$line] = 'source';
            }
        }
        foreach ($targetLines as $line => $count) {
            $comparisonResult[$line] = 'target';
        }
        ksort($comparisonResult);

        return $comparisonResult;
    }
}
