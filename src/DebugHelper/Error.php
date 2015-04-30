<?php
namespace DebugHelper;

class Error
{
    /**
     * Overrides the deafult PHP error handler providing extra info about the source of the error.
     *
     * @see http://es2.php.net/manual/en/function.set-error-handler.php
     * @var integer $errno Error code.
     * @var string $errstr Message information of the error.
     * @var string $errfile File source of the error.
     * @var integer $errline Line number source of the error.
     * @return boolean True to avoid the default error handler.
     */
    public static function handler($errno, $errstr, $errfile, $errline)
    {
        $class = 'error_handler_notice';
        switch ($errno) {
            case E_USER_ERROR:
                $type = 'Error';
                $class = 'error_handler_error';
                break;
            case 2:
                $type = 'Warning';
                $class = 'error_handler_warning';
                break;
            case 8:
                $trace = xdebug_get_function_stack();
                if (false !== strstr($errfile, '/debug.ctrl.php')) {
                    return true;
                }
                if (false !== strstr($errfile, '/templates_c/')) {
                    return true;
                }
                $type = 'Notice';
                break;
            default:
                $type = "Unknown ($errno)";
        }
        $id = 'error_' . uniqid();
        if (\DebugHelper::isCli()) {
            echo <<<ERROR
------------------------
Php $type $errstr
    in $errfile:$errline
------------------------

ERROR;
        } else {
            echo <<<ERROR
<div class="error_handler $class" id="$id">
    <strong>Php $type</strong><b>$errstr</b> in <a href="CodeBrowser:$errfile:$errline"><b>$errfile</b> on line <b>$errline</b></a></span>
</div>
ERROR;
        }
        return true;
    }
}