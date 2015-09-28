<?php
namespace DebugHelper\Gui;

class Template
{
    protected $twig;
    protected $base;
    protected $data;

    public function __construct()
    {
        $base_path = __DIR__ . '/../../../tpl/';
        $loader = new \Twig_Loader_Filesystem($base_path);
        $this->twig = new \Twig_Environment($loader, array('debug' => true));
        $this->twig->addExtension(new \Twig_Extension_Debug());
        $base_url = dirname($_SERVER['SCRIPT_NAME']);
        $this->assign('BASE_URL', $base_url);
    }

    /**
     * @param string $variable
     * @param mixed $value
     */
    public function assign($variable, $value)
    {
        $this->data[$variable] = $value;
    }

    /**
     * Load the template.
     *
     * @param string $view_file Template to load
     *
     * @return string
     */
    public function fetch($view_file)
    {
        return $this->twig->render($view_file . '.html.twig', $this->data);
    }
}