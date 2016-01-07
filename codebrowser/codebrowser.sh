#!/usr/bin/php
<?php
$idePaths = array(
    'sublime' => '/opt/sublime_text/sublime_text'
);

$parameter = isset($argv[1]) ? $argv[1] : 'codebrowser:/vagrant/shop-new/web/app_dev.php:42';

if (!preg_match('/^codebrowser:(?P<path>.*):(?P<line>\d+)$/', $parameter, $parameters)) {
    exit;
}

$fileHandler = fopen(__DIR__ . '/codebrowser_rules.txt', 'r');
while(!feof($fileHandler)) {
    $line = fgets($fileHandler);
    // DebugTemp (Notepad): \\vagrant\\shared\\vendor\\kizilare\\phpdebug\\temp\\ => D:\Ansible\online-convert-api\shared\vendor\kizilare\phpdebug\temp\

    if (preg_match('/(.*):\s*(?P<source>.*?)\s*=>\s*(?P<target>.*)$/', $line, $matches)) {
        if (strpos($parameters['path'], $matches['source']) === 0) {
            echo $line . PHP_EOL;
            echo $matches['source'] . PHP_EOL;
            echo $parameters['path'] . PHP_EOL;
            $targetPath = str_replace($matches['source'], $matches['target'], $parameters['path']);
            var_dump($targetPath);
            if (is_file($targetPath)) {
                $command = "{$idePaths['sublime']} \"{$targetPath}:{$parameters['line']}\"";
                exec($command);
            }
            exit;
        }
    }
}
