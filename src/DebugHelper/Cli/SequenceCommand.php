<?php
namespace DebugHelper\Cli;

class SequenceCommand extends Abstracted
{
    /**
     * Type call for a line
     */
    const STEP_CALL = 1;

    /**
     * Type return for a line
     */
    const STEP_RETURN = 2;

    /**
     * Type call and return for a line
     */
    const STEP_CALL_RETURN = 3;

    /**
     * Type unknown for a line
     */
    const STEP_UNKNOWN = 3;

    /**
     * Current call must not be ignored
     */
    const IGNORE_NONE = 0;

    /**
     * Following calls will be ignored
     */
    const IGNORE_NAMESPACE = 1;

    /**
     * Following and current calls will be ignored
     */
    const IGNORE_CALL = 2;

    /**
     * The history for the current depth is a method call
     */
    const HISTORY_METHOD = 'method';

    /**
     * The history for the current depth is a call to the same class
     */
    const HISTORY_AUTO_CALL= 'auto_call';

    /**
     * Processed lines information
     *
     * @var array
     */
    protected $steps;

    /**
     * Depth where all calls bellow are ignored
     *
     * @var integer
     */
    protected $ignoreDepth;

    /**
     * Name of the namespace being ignored
     *
     * @var string
     */
    protected $ignoreNamespace;

    /**
     * List of ignored namespaces applied and steps ignored by them
     *
     * @var array
     */
    protected $ignoreCount;

    /**
     * Stats about the process for user feedback
     *
     * @var array
     */
    protected $stats;

    /**
     * Breadcrumbs of calls by depth
     *
     * @var array
     */
    protected $history;

    /**
     * Source for the calls
     *
     * @var array
     */
    protected $source;

    /**
     * Identifier for the last call made
     *
     * @var string
     */
    protected $lastCall;

    /**
     * Namespaces ignored, all calls bellow this namespaces will be ignored
     *
     * @var array
     */
    protected $ignoredNamespaces = [
        'Symfony\Component\DependencyInjection\ContainerAware'              => self::IGNORE_CALL,
        'Composer\Autoload\ClassLoader'                                     => self::IGNORE_CALL,
        'ReflectionObject'                                                  => self::IGNORE_CALL,
        'ReflectionClass'                                                   => self::IGNORE_CALL,
        'Composer'                                                          => self::IGNORE_NAMESPACE,
        'Symfony\Component\HttpKernel\Bundle\Bundle'                        => self::IGNORE_CALL,
        'Symfony\Component\ExpressionLanguage\ExpressionFunction'           => self::IGNORE_CALL,
        'Doctrine'                                                          => self::IGNORE_NAMESPACE,
        'Symfony\Component\HttpFoundation\Request'                          => self::IGNORE_NAMESPACE,
        'Monolog'                                                           => self::IGNORE_NAMESPACE,
        'DebugHelper'                                                       => self::IGNORE_NAMESPACE,
        'QaamGo\RestApiBundle\Serializer\Entity'                            => self::IGNORE_NAMESPACE,
        'QaamGo\RestApiBundle\Api\Validation\Schema\Constraint'             => self::IGNORE_NAMESPACE,
        'Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher'   => self::IGNORE_NAMESPACE,
        'Symfony\Component\DependencyInjection\Container'                   => self::IGNORE_CALL,
        'Twig_Environment'                                                  => self::IGNORE_NAMESPACE,
        'QaamGo\RestApiBundle\Api\Validation\RequestValidator'              => self::IGNORE_NAMESPACE,
        'Symfony\Bundle\FrameworkBundle\Controller\Controller'              => self::IGNORE_NAMESPACE,
        'Symfony\Component\HttpFoundation\JsonResponse'                     => self::IGNORE_NAMESPACE,
        'GuzzleHttp\Client'                                                 => self::IGNORE_NAMESPACE,
        'GuzzleHttp\Message\Response'                                       => self::IGNORE_NAMESPACE,
        'Qaamgo\OnlineConvertApiBundle\Entity\JobRepository'                => self::IGNORE_NAMESPACE,
        'Qaamgo\OnlineConvertApiBundle\Factory\Job'                         => self::IGNORE_NAMESPACE,
        'Qaamgo\OnlineConvertApiBundle\Handler\OnlineConvertApi'            => self::IGNORE_NAMESPACE,
        'Doctrine\Common\Collections\ArrayCollection'                       => self::IGNORE_CALL,
        'PhpAmqpLib'                                                        => self::IGNORE_NAMESPACE
    ];

    /**
     * Line to skip to
     *
     * @var int
     */
    protected $skipTo;

    /**
     * Execute the command line
     */
    public function run()
    {
        $file = \DebugHelper::get('debug_dir') . $this->arguments[2];
        preg_match('/^(.*\/)?(?P<id>.*?)(\.\w*)?$/', $file, $matches);
        $fileId = $matches['id'];
        print_r($fileId);

        if (!is_file('temp/' . $fileId . '.xt')) {
            throw new \Exception("Error Processing file $fileId");
        }

        $this->stats = [
            'lines'     => 0,
            'steps'     => 0,
            'ignored'   => 0,
            'invalid'   => 0,
            'methods'   => 0,
            'applied'   => 0,
            'returns'   => 0,
            'self'      => 0,
            'range'     => 0
        ];

        $this->skipTo = false;
        if (!empty($this->arguments['skip-to'])) {
            $this->skipTo = $this->arguments['skip-to'];
        }
        $this->generateFiles(
            $fileId,
            empty($this->arguments['limit']) ? 1000000 : $this->arguments['limit'],
            !empty($this->arguments['no-cache'])
        );
        echo "Finished $fileId!!!\n";
    }

    /**
     * Generates the output
     *
     * @param string $fileId Identifier of the file to process
     * @param int $maxLines Max lines to parse
     * @param bool $ignoreCache Ignores the cache and regenerates the file
     */
    protected function generateFiles($fileId, $maxLines, $ignoreCache = false)
    {
        if (!$ignoreCache && is_file('temp/' . $fileId . '.json')) {
            $data = json_decode(file_get_contents('temp/' . $fileId . '.json'), true);

            $this->steps = $data;
        } else {
            $this->steps = [];
            $this->source = [];
            $this->ignoreCount = [];
            $this->history = [];

            $fileIn = fopen('temp/' . $fileId . '.xt', 'r');
            while (!feof($fileIn) && $maxLines-- > 0) {
                $line = fgets($fileIn);
                $this->stats['lines']++;
                $this->processInputLine($line);
            }
            fclose($fileIn);
            echo "Ignored classes\n";
            ksort($this->ignoreCount);
            \DebugHelper::dump($this->ignoreCount);
            $output = json_encode($this->steps, JSON_PRETTY_PRINT);
            if ($output == false) {
                var_dump(json_last_error());
                var_dump(json_last_error_msg());
            } else {
                file_put_contents('temp/' . $fileId . '.xt.diag.json', $output);
            }
        }
        $this->writeLog('temp/' . $fileId);
    }

    /**
     * Writes log to debug what has loaded the sequence
     *
     * @param string $file Original input file
     */
    protected function writeLog($file)
    {
        $fileOut = fopen($file . '.log', 'w');

        foreach ($this->steps as $step) {
            $indent = str_repeat(' ', $step['depth']);
            if ($step['type'] == self::STEP_CALL) {
                $stepLog = sprintf(
                    '%06d %s%s->%s (%s)%s',
                    $step['line_no'],
                    $indent,
                    $step['namespace'],
                    $step['method'],
                    $step['source'],
                    PHP_EOL
                );

                fwrite($fileOut, $stepLog);
            }
        }
        fclose($fileOut);
    }

    /**
     * Parses the information
     *
     * @param string $line Line contents
     */
    protected function processInputLine($line)
    {
        if ($this->stats['lines'] % 1000 == 0) {
            $this->showOutput();
        }

        $lineInfo = $this->getLineInfo($line);

        if ($lineInfo['type'] === self::STEP_UNKNOWN) {
            $this->debug('Invalid');
            $this->stats['invalid']++;
            return;
        }

        if ($this->mustSkip($lineInfo)) {
            $this->stats['range']++;
            return;
        }

        if ($lineInfo['type'] === self::STEP_RETURN) {
            $this->stats['returns']++;
            $this->processReturn($lineInfo);
            return;
        } else {
            $this->processCall($lineInfo);
        }
    }

    /**
     * Parses the information
     *
     * @param array $lineInfo Line contents
     * @return bool
     */
    protected function mustSkip($lineInfo)
    {
        if ($this->skipTo === false) {
            return false;
        }

        if ($this->stats['lines'] >= $this->skipTo) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Parses the information
     *
     * @param array $lineInfo Line contents
     */
    protected function processCall($lineInfo)
    {
        $depth = $lineInfo['depth'];
        if ($this->ignoreDepth) {
            if ($depth > $this->ignoreDepth) {
                $this->debug("Skipped. Ignored call > {$this->ignoreDepth}", $depth);
                $this->ignoreCount[$this->ignoreNamespace]++;
                $this->stats['ignored']++;
                return;
            } else {
                $this->debug("Disabling ignore in call", $depth);
                $this->ignoreDepth = false;
            }
        }
        if (preg_match('/^(?P<namespace>[^\(]+)(::|->)(?P<method>[^\(]+).*$/', $lineInfo['call'], $matches)) {
            list($matchedEntry, $ignore) = $this->checkIgnoredNamespaces($matches['namespace']);
            if (self::IGNORE_NONE !== $ignore) {
                $this->debug("Ignoring. Ignored below for $matchedEntry", $depth);
                $this->ignoreNamespace = $matchedEntry;
                if (!isset($this->ignoreCount[$matchedEntry])) {
                    $this->ignoreCount[$this->ignoreNamespace] = 0;
                }
                $this->ignoreDepth = $lineInfo['depth'];
                $this->stats['applied']++;
                if ($ignore === self::IGNORE_CALL) {
                    $this->ignoreCount[$this->ignoreNamespace]++;
                    $this->debug("Skipped. Ignored call for current call", $depth);
                    return;
                }
            }
        } else {
            $this->history[$depth] = self::HISTORY_METHOD;
            $this->stats['methods']++;
            $this->debug("Skipped. Method call", $depth);
            return;
        }

        $this->source[$depth + 1] = $matches['namespace'];
        $source = isset($this->source[$depth]) ? $this->source[$depth] : 'root';
        if ($source == $matches['namespace']) {
            $this->stats['self']++;
            $this->debug("Skipped. Self call", $depth);
            $this->history[$depth] = self::HISTORY_AUTO_CALL;
            return;
        }

        $this->stats['steps']++;
        $id = uniqid();

        $step = [
            'line_no'           => $this->stats['lines'],
            'depth'             => $depth,
            'path'              => $lineInfo['path'],
            'source'            => $source,
            'namespace'         => $matches['namespace'],
            'method'            => $matches['method'],
            'info'              => '',
            'type'              => self::STEP_CALL
        ];
        $this->debug("Method call. {$matches['namespace']}->{$matches['method']}", $depth);
        $this->steps[$id] = $step;
        $this->history[$depth] = $id;
        $this->lastCall = $id;
    }

    /**
     * Parses the information
     *
     * @param array $lineInfo Line contents
     */
    protected function processReturn($lineInfo)
    {
        $depth = $lineInfo['depth'];
        if ($this->ignoreDepth) {
            if ($depth > $this->ignoreDepth) {
                $this->ignoreCount[$this->ignoreNamespace]++;
                $this->debug("Skipped. Ignored return > {$this->ignoreDepth}", $depth);
                $this->stats['ignored']++;
                return;
            } else {
                $this->debug("Disabling ignore in return", $depth);
                $this->ignoreDepth = false;
            }
        }

        if (!isset($this->history[$depth])) {
            return;
        }
        $callerId = $this->history[$depth];

        if (!empty($this->steps[$callerId]['end'])) {
            $this->debug("Skipped. The original method already has the return", $depth);
            return;
        }

        if ($this->history[$depth] == self::HISTORY_METHOD) {
            $this->debug("Skipped. Ignored return for method", $depth);
            return;
        } elseif ($this->history[$depth] == self::HISTORY_AUTO_CALL) {
            $this->debug("Skipped. Ignored return for auto call", $depth);
            return;
        }

        if ($this->lastCall == $callerId) {
            $this->steps[$callerId]['type'] = self::STEP_CALL_RETURN;
            $this->steps[$callerId]['return_line_no'] = $this->stats['lines'];
            $this->steps[$callerId]['response'] = $lineInfo['response'];
            $this->debug("Return in the same thread", $depth);
        } else {
            $this->stats['steps']++;
            $id = uniqid();
            $this->steps[$callerId]['end'] = $id;
            $step = [
                'line_no'           => $this->stats['lines'],
                'depth'             => $depth,
                'source'            => $this->steps[$callerId]['namespace'],
                'namespace'         => $this->steps[$callerId]['source'],
                'response'          => $lineInfo['response'],
                'info'              => '',
                'type'              => self::STEP_RETURN,
                'from'              => $callerId
            ];
            $this->debug("Return", $depth);
            $this->steps[$id] = $step;
        }
    }

    /**
     * Shows debug / progress information to the output
     */
    protected function showOutput()
    {
        if (!empty($this->arguments['debug'])) {
            return;
        }
        $output = <<<OUTPUT
      %5d steps
      %5d ignored (%06d applies)
      %5d range
      %5d returns
      %5d self
      %5d error(s)
      %5d methods
      ------
Lines %5d


OUTPUT;
        printf(
            $output,
            $this->stats['steps'],
            $this->stats['ignored'],
            $this->stats['applied'],
            $this->stats['range'],
            $this->stats['returns'],
            $this->stats['self'],
            $this->stats['invalid'],
            $this->stats['methods'],
            $this->stats['lines']
        );
    }

    /**
     * Checks if a given namespace must be ignored from the debug
     *
     * @param string $namespace
     * @return bool
     */
    protected function checkIgnoredNamespaces($namespace)
    {
        foreach ($this->ignoredNamespaces as $ignoredNamespace => $type) {
            if (substr($namespace, 0, strlen($ignoredNamespace)) == $ignoredNamespace) {
                return [$ignoredNamespace, $type];
            }

        }
        return [null, self::IGNORE_NONE];
    }

    /**
     * Process a single line a returns the information about it
     *
     * @param string $line
     * @return array
     */
    protected function getLineInfo($line)
    {
        $callRegExp = '/(?P<time>\d+\.\d+)\s+(?P<memory>\d+)(?P<depth>\s+)->\s+(?P<call>.*)\s+(?P<path>[^\s+]+)$/';
        $returnRegExp = '/(?P<time>\d+\.\d+)\s+(?P<memory>\d+)(?P<depth>\s+)>=>\s+(?P<response>.*)$/';
        if (preg_match($callRegExp, $line, $matches)) {
            $matches['depth'] = ceil(strlen($matches['depth']) / 2);
            $matches['path_length'] = count(explode('/', $matches['path']));
            $matches['type'] = self::STEP_CALL;
            return $matches;
        } elseif (preg_match($returnRegExp, $line, $matches)) {
            $matches['type'] = self::STEP_RETURN;
            $matches['depth'] = ceil(strlen($matches['depth']) / 2);
            $matches['type'] = self::STEP_RETURN;
            return $matches;
        } else {
            return ['type' => self::STEP_UNKNOWN];
        }
    }

    /**
     * Clean an array of numeric keys
     *
     * @param array $array Array to clean
     * @return array
     */
    protected function cleanArray($array)
    {
        $cleanArray = [];
        foreach ($array as $key => $value) {
            if (!is_numeric($key)) {
                $cleanArray[$key] = $value;
            }
        }
        return $cleanArray;
    }

    /**
     * Debugs information to the output
     *
     * @param string $message Information for debug
     * @param int $depth Depth for the current message
     */
    protected function debug($message, $depth = 0)
    {
        if (!empty($this->arguments['debug'])) {
            printf('%04d [%02d]: %s%s', $this->stats['lines'], $depth, $message, PHP_EOL);
        }
    }
}
