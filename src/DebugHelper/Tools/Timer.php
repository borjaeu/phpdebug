<?php
namespace DebugHelper\Tools;

use DebugHelper\Styles;

class Timer
{
    /**
     * Start time from the beginning of the timer.
     *
     * @var float
     */
    private $startTime;

    /**
     * Last time where a delta was shown.
     *
     * @var float
     */
    private $lastTime;

    /**
     * @var string
     */
    private $runningCommand;

    /**
     * Init the timer.
     */
    public function __construct()
    {
        $this->startTime = $this->lastTime = microtime(true);
        $timerFile = $this->getTimerPath();
        if (is_file($timerFile)) {
            unlink($timerFile);
        }
    }

    /**
     * Shows partial and total time elapsed with the message.
     *
     * @param string $message Message to show.
     */
    public function delta($group, $message = '')
    {
        $currentTime = microtime(true);
        $totalTime = $currentTime - $this->startTime;
        $partialTime = $currentTime - $this->lastTime;

        $this->lastTime = $currentTime;

        if ($this->runningCommand) {
            file_put_contents($this->getTimerPath(), sprintf('%04.04f %04.04f %s%s', $partialTime, $totalTime, $this->runningCommand, PHP_EOL), FILE_APPEND);
        }
        $this->runningCommand = sprintf('[%s] %s', $group, $message);
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

    /**
     * Save the last running command if any
     */
    public function __destruct()
    {
        $this->delta('END');
    }


}
