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
     * Debug information
     *
     * @var bool
     */
    protected $debug;

    /**
     * Namespaces ignored, all calls bellow this namespaces will be ignored
     *
     * @var array
     */
    protected $ignoredNamespaces = [
        //        'Symfony\Component\HttpKernel\Kernel',
        'Symfony\Component\DependencyInjection\ContainerAware'              => self::IGNORE_CALL,
        'Composer\Autoload\ClassLoader'                                     => self::IGNORE_CALL,
//        'Composer'                                                          => self::IGNORE_NAMESPACE,
        'Symfony\Component\HttpKernel\Bundle\Bundle'                        => self::IGNORE_CALL,
        'Symfony\Component\ExpressionLanguage\ExpressionFunction'           => self::IGNORE_CALL,
        'Symfony\Component\HttpFoundation'                                  => self::IGNORE_CALL,
        'Doctrine'                                                          => self::IGNORE_NAMESPACE,
        'Monolog'                                                           => self::IGNORE_NAMESPACE,
//        'DebugHelper'                                                       => self::IGNORE_NAMESPACE,
        'QaamGo\RestApiBundle\Serializer\Entity'                            => self::IGNORE_NAMESPACE,
        'QaamGo\RestApiBundle\Api\Validation\Schema\Constraint'             => self::IGNORE_NAMESPACE,
        'Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher'   => self::IGNORE_NAMESPACE,
        'Symfony\Component\DependencyInjection\Container'                   => self::IGNORE_NAMESPACE,
        'Twig_Environment'                                                  => self::IGNORE_NAMESPACE
    ];

    /**
     * Source for the calls
     *
     * @var array
     */
    protected $source;

    /**
     * Namespaces used in the current request
     *
     * @var array
     */
    protected $namespaces;

    /**
     * Execute the command line
     */
    public function run()
    {
        $file = \DebugHelper::getDebugDir() . $this->arguments[2];

        if (!is_file($file . '.xt')) {
            throw new \Exception("Error Processing file $file");
        }

        $this->stats = [
            'lines'     => 0,
            'steps'     => 0,
            'ignored'   => 0,
            'invalid'   => 0,
            'methods'   => 0,
            'applied'   => 0,
            'returns'   => 0,
            'self'      => 0
        ];

        $this->debug = in_array('--debug', $this->arguments);
        $this->generateFiles($file, in_array('--no-cache', $this->arguments));

        echo "Finished $file!!!\n";
    }

    /**
     * Generates the output
     *
     * @param string $file File to process
     * @param bool $ignoreCache Ignores the cache and regenerates the file
     */
    protected function generateFiles($file, $ignoreCache = false)
    {
        if (!$ignoreCache && is_file($file . '.json')) {
            $data = json_decode(file_get_contents($file . '.json'), true);

            $this->steps = $data['steps'];
            $this->namespaces = $data['namespaces'];
        } else {
            $this->steps = [];
            $this->source = [];
            $this->ignoreCount = [];
            $this->history = [];
            $this->namespaces = ['root' => 0];

            $fileIn = fopen($file . '.xt', 'r');
            $maxLines = 1000000;
//            $maxLines = 50;
            while (!feof($fileIn) && $maxLines-- > 0) {
                $line = fgets($fileIn);
                $this->stats['lines']++;
                $this->processInputLine($line);
            }
            fclose($fileIn);
            print_r($this->namespaces);
            print_r($this->ignoreCount);

            file_put_contents($file . '.json', json_encode([
                'steps'      => $this->steps,
                'namespaces' => $this->namespaces
            ], JSON_PRETTY_PRINT));
        }
//        $this->writeLog($file);
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
            $stepLog = <<<STEP
$indent{$step['namespace']}->{$step['method']}  ({$step['source']})

STEP;
            fwrite($fileOut, $stepLog);

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
        } elseif ($lineInfo['type'] === self::STEP_RETURN) {
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
                    $this->ignoreDepth--;
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
        $this->registerNamespace($matches['namespace']);
        $id = uniqid();
        $step = [
            'line_no'           => $this->stats['lines'],
            'depth'             => $depth,
            'path'              => $lineInfo['path'],
            'source'            => $source,
            'namespace'         => $matches['namespace'],
            'method'            => $matches['method'],
            'type'              => self::STEP_CALL
        ];
        $this->debug("Method call. {$matches['namespace']}->{$matches['method']}", $depth);
        $this->steps[$id] = $step;
        $this->history[$depth] = $step;
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
            if ($depth >= $this->ignoreDepth) {
                $this->ignoreCount[$this->ignoreNamespace]++;
                $this->debug("Skipped. Ignored return >= {$this->ignoreDepth}", $depth);
                $this->stats['ignored']++;
                return;
            } else {
                $this->debug("Disabling ignore in return", $depth);
                $this->ignoreDepth = false;
            }
        }

        if (!isset($this->history[$depth])) {
//            var_dump($this->ignoreDepth);
//            print_r($lineInfo);
//            print_r($this->steps);
//            print_r($this->stats);
            die(sprintf("<pre><a href=\"codebrowser:%s:%d\">DIE</a></pre>", __FILE__, __LINE__));
            exit;
        }
        if ($this->history[$depth] == self::HISTORY_METHOD) {
            $this->debug("Skipped. Ignored return for method", $depth);
            return;
        } elseif ($this->history[$depth] == self::HISTORY_AUTO_CALL) {
            $this->debug("Skipped. Ignored return for auto call", $depth);
            return;
        }

        $this->stats['steps']++;
        $id = uniqid();
        $step = [
            'line_no'           => $this->stats['lines'],
            'depth'             => $depth,
            'source'            => $this->history[$depth]['namespace'],
            'namespace'         => $this->history[$depth]['source'],
            'response'          => $lineInfo['response'],
            'type'              => self::STEP_RETURN
        ];
        $this->debug("Return", $depth);
        $this->steps[$id] = $step;
    }

    /**
     * Shows debug / progress information to the output
     */
    protected function showOutput()
    {
        $output = <<<OUTPUT
      %5d steps
      %5d ignored (%06d applies)
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
            $this->stats['returns'],
            $this->stats['self'],
            $this->stats['invalid'],
            $this->stats['methods'],
            $this->stats['lines']
        );
    }

    /**
     * Register used namespaces
     *
     * @param string $namespace Namespace
     */
    protected function registerNamespace($namespace)
    {
        if (!isset($this->namespaces[$namespace])) {
            $this->namespaces[$namespace] = count($this->namespaces);
        }
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
        if ($this->debug) {
            printf('%04d [%02d]: %s%s', $this->stats['lines'], $depth, $message, PHP_EOL);
        }
    }
}
