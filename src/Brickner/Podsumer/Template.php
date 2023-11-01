<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

use function \htmlspecialchars;

class Template
{
    protected Main $main;
    protected string $base_template;
    protected string $template_dir;

    public function __construct(Main $main)
    {
        $this->main = $main;
        $this->base_template = $this->main->getConf('podsumer', 'base_template');
        $this->template_dir = $this->main->getConf('podsumer', 'template_dir');
    }

    static public function render(Main $main, string $template_name, array $vars = [])
    {
        $t = new self($main);
        $base_template = $main->getConf('podsumer', 'base_template');
        $t->renderTemplate($template_name, $vars, $base_template . '.html.php');
    }

    static public function renderXml(Main $main, string $template_name, array $vars = [])
    {
        $t = new self($main);
        $t->renderTemplate('', $vars, $template_name . '.xml.php');
    }

    protected function renderTemplate(string $template_name, array $vars, string $base_template)
    {
        $PAGE_TITLE = $this->main->getConf('podsumer', 'default_page_title');
        $LANGUAGE = $this->main->getConf('podsumer', 'language');

        $BODY = '';
        if (!empty($template_name)) {
            $BODY = $this->getTemplatePath($template_name . '.html.php');
        }

        $cleaned_vars = $this->encodeVars($vars);
        extract($cleaned_vars);
        include($this->getTemplatePath($base_template));
    }

    protected function getTemplatePath(string $template_name)
    {
        return $this->main->getInstallPath() .
            $this->template_dir .
            \DIRECTORY_SEPARATOR .
            $template_name;
    }

    protected function encodeVars(array $vars): array
    {
        array_walk_recursive($vars, function (&$var) {
            $var = htmlentities(strip_tags(strval($var)), ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, '', false);
        });

        return $vars;
    }
}

