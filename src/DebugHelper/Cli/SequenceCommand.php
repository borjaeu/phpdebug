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
     * Processed lines information
     *
     * @var array
     */
    protected $lines;

    /**
     * Depth where all calls bellow are ignored
     *
     * @var integer
     */
    protected $ignoreDepth;

    /**
     * Stats about the process for user feedback
     *
     * @var array
     */
    protected $stats;

    /**
     * Namespaces ignored, all calls bellow this namespaces will be ignored
     *
     * @var array
     */
    protected $ignoredNamespaces = [
        //        'Symfony\Component\HttpKernel\Kernel',
        'Symfony\Component\DependencyInjection\ContainerAware'              => self::IGNORE_CALL,
        'Composer\Autoload\ClassLoader'                                     => self::IGNORE_CALL,
        'Symfony\Component\HttpKernel\Bundle\Bundle'                        => self::IGNORE_CALL,
        'Symfony\Component\ExpressionLanguage\ExpressionFunction'           => self::IGNORE_CALL,
        'Symfony\Component\HttpFoundation'                                  => self::IGNORE_CALL,
        'Doctrine'                                                          => self::IGNORE_NAMESPACE,
        'Monolog'                                                           => self::IGNORE_NAMESPACE,
        'Composer'                                                          => self::IGNORE_NAMESPACE,
        'DebugHelper'                                                       => self::IGNORE_NAMESPACE,
        'QaamGo\RestApiBundle\Serializer\Entity'                            => self::IGNORE_NAMESPACE,
        'QaamGo\RestApiBundle\Api\Validation\Schema\Constraint'             => self::IGNORE_NAMESPACE,
        'Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher'   => self::IGNORE_NAMESPACE,
        'Symfony\Component\DependencyInjection\Container'                   => self::IGNORE_NAMESPACE,
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
        $file = \DebugHelper::getDebugDir() . $this->arguments[2] . '.xt';

        if (!is_file($file)) {
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

        $this->generateFiles($file);

        echo "$file\n";
    }

    /**
     * Generates the output
     *
     * @param string $file File to process
     */
    protected function generateFiles($file)
    {
        if (is_file($file . '.json')) {
            $data = json_decode(file_get_contents($file . '.json'), true);

            $this->lines = $data['steps'];
            $this->namespaces = $data['namespaces'];
        } else {
            $this->lines = [];
            $this->source = [];
            $this->namespaces = ['root' => 0];

            $fileIn = fopen($file, 'r');
            $maxLines = 1000000;
            while (!feof($fileIn) && $maxLines-- > 0) {
                $line = fgets($fileIn);
                $this->stats['lines']++;
                $this->processInputLine($line);
            }
            fclose($fileIn);
            print_r($this->namespaces);

            file_put_contents($file . '.json', json_encode([
                'steps' => $this->lines,
                'namespaces' => $this->namespaces
            ], JSON_PRETTY_PRINT));
        }
        $this->writeLog($file);
    }

    /**
     * Writes log to debug what has loaded the sequence
     *
     * @param string $file Original input file
     */
    protected function writeLog($file)
    {
        $fileOut = fopen($file . '.log', 'w');

        foreach ($this->lines as $line) {
            $indent = str_repeat(' ', $line['depth']);
            $step = <<<STEP
$indent{$line['namespace']}->{$line['method']}  ({$line['source']})

STEP;
            fwrite($fileOut, $step);

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
            $this->stats['invalid']++;
            return;
        } elseif ($lineInfo['type'] === self::STEP_RETURN) {
            $this->stats['returns']++;
            return;
        }
        if ($this->ignoreDepth) {
            if ($lineInfo['depth'] > $this->ignoreDepth) {
                $this->stats['ignored']++;
                return;
            } else {
                $this->ignoreDepth = false;
            }
        }
        if (preg_match('/^(?P<namespace>[^\(]+)(::|->)(?P<method>[^\(]+).*$/', $lineInfo['call'], $matches)) {
            if (self::IGNORE_NONE !== $ignore = $this->isIgnoredNamespace($matches['namespace'], $matches['method'])) {
                $this->ignoreDepth = $lineInfo['depth'];
                $this->stats['applied']++;
                if ($ignore === self::IGNORE_CALL) {
                    return;
                }
            }
        } else {
            $this->stats['methods']++;
            return;
        }

        $depth = $lineInfo['depth'];
        $this->source[$depth + 1] = $matches['namespace'];
        $source = isset($this->source[$depth]) ? $this->source[$depth] : 'root';
        if ($source == $matches['namespace']) {
            $this->stats['self']++;
            return;
        }

        $this->stats['steps']++;
        $this->registerNamespace($matches['namespace']);
        $this->lines[$this->stats['steps']] = [
            'line_no'           => $this->stats['lines'],
            'depth'             => $depth,
            'path'              => $lineInfo['path'],
            'source'            => $source,
            'namespace'         => $matches['namespace'],
            'method'            => $matches['method']
        ];
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
    protected function isIgnoredNamespace($namespace)
    {
//        if (in_array($namespace, $this->ignoredCalls)) {
//            return self::IGNORE_CALL;
//        }
        foreach ($this->ignoredNamespaces as $ignoredNamespace => $type) {
            if (substr($namespace, 0, strlen($ignoredNamespace)) == $ignoredNamespace) {
                return $type;
            }

        }
        return self::IGNORE_NONE;
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
}
