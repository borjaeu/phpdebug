<?php
namespace DebugHelper\Cli;

use Symfony\Component\Console\Command\Command;

abstract class Abstracted extends Command
{
    /**
     * @param string $file
     * @return string
     */
    protected function getIdFromFile($file)
    {
        preg_match('/^(.*\/)?(?P<id>.*?)(\.\w*)?$/', $file, $matches);

        return $matches['id'];
    }
}
