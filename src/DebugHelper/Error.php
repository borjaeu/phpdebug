<?php
namespace DebugHelper;

class Error
{
    protected static $ignored_errors = array();

    public static function ignoreErrors()
    {
        $errors = func_get_args();
        self::$ignored_errors = array_merge(self::$ignored_errors, $errors);
    }

    /**
     * Overrides the default PHP error handler providing extra info about the source of the error.
     *
     * @see http://es2.php.net/manual/en/function.set-error-handler.php
     * @var integer $code Error code.
     * @var string $message Message information of the error.
     * @var string $file File source of the error.
     * @var integer $line Line number source of the error.
     * @return boolean True to avoid the default error handler.
     */
    public static function handler($code, $message, $file, $line)
    {
        $error_code = md5($code . $message . $file . $line);
        if (in_array($error_code, self::$ignored_errors)) {
            return true;
        }
        $class = 'error_handler_notice';
        switch ($code) {
            case E_USER_ERROR:
                $type = 'Error';
                $class = 'error_handler_error';
                break;
            case 2:
                $type = 'Warning';
                $class = 'error_handler_warning';
                break;
            case 8:
                $type = 'Notice';
                break;
            default:
                $type = "Unknown($code)";
        }
        $id = 'error_' . uniqid();
        if (\DebugHelper::isCli()) {
            echo <<<ERROR
------------------------
Php $type $message
    in $file:$line
------------------------

ERROR;
        } else {
            echo <<<ERROR
<div class="error_handler $class" id="$id">
    <strong>Php $type</strong> $message in <a href="codebrowser:$file:$line">$file on line $line</a></span> $error_code
</div>
ERROR;
        }
        return true;
    }
}