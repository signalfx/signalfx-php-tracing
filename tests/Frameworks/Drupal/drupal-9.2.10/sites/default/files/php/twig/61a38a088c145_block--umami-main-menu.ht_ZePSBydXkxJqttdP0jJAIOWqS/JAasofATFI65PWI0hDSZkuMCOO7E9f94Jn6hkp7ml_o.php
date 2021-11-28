<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;

/* core/profiles/demo_umami/themes/umami/templates/components/navigation/block--umami-main-menu.html.twig */
class __TwigTemplate_7d50cfd570264ae8b20999f67ae9fb96adc690c79527c07f85dd3599898ff837 extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
            'content' => [$this, 'block_content'],
        ];
        $this->sandbox = $this->env->getExtension('\Twig\Extension\SandboxExtension');
        $this->checkSecurity();
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 35
        $context["classes"] = [0 => "block", 1 => "block-menu", 2 => "navigation", 3 => (("menu-" . \Drupal\Component\Utility\Html::getClass($this->sandbox->ensureToStringAllowed(        // line 39
($context["derivative_plugin_id"] ?? null), 39, $this->source))) . "__wrapper")];
        // line 42
        echo "
<div class=\"menu-main-togglewrap\">
  <button type=\"button\" name=\"menu_toggle\" class=\"menu-main-toggle\" data-drupal-selector=\"menu-main-toggle\" aria-label=\"Toggle the menu\">";
        // line 44
        $this->loadTemplate(($this->extensions['Drupal\Core\Template\TwigExtension']->getActiveThemePath() . "/images/svg/menu-icon.svg"), "core/profiles/demo_umami/themes/umami/templates/components/navigation/block--umami-main-menu.html.twig", 44)->display($context);
        echo "</button>
</div>

";
        // line 47
        $context["heading_id"] = ($this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["attributes"] ?? null), "id", [], "any", false, false, true, 47), 47, $this->source) . \Drupal\Component\Utility\Html::getId("-menu"));
        // line 48
        echo "<nav role=\"navigation\" aria-labelledby=\"";
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["heading_id"] ?? null), 48, $this->source), "html", null, true);
        echo "\"";
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\Core\Template\TwigExtension']->withoutFilter($this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["attributes"] ?? null), "addClass", [0 => ($context["classes"] ?? null)], "method", false, false, true, 48), 48, $this->source), "role", "aria-labelledby"), "html", null, true);
        echo ">
  ";
        // line 50
        echo "  ";
        if ( !twig_get_attribute($this->env, $this->source, ($context["configuration"] ?? null), "label_display", [], "any", false, false, true, 50)) {
            // line 51
            echo "    ";
            $context["title_attributes"] = twig_get_attribute($this->env, $this->source, ($context["title_attributes"] ?? null), "addClass", [0 => "visually-hidden"], "method", false, false, true, 51);
            // line 52
            echo "  ";
        }
        // line 53
        echo "  ";
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["title_prefix"] ?? null), 53, $this->source), "html", null, true);
        echo "
  <h2";
        // line 54
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["title_attributes"] ?? null), "setAttribute", [0 => "id", 1 => ($context["heading_id"] ?? null)], "method", false, false, true, 54), 54, $this->source), "html", null, true);
        echo ">";
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["configuration"] ?? null), "label", [], "any", false, false, true, 54), 54, $this->source), "html", null, true);
        echo "</h2>
  ";
        // line 55
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["title_suffix"] ?? null), 55, $this->source), "html", null, true);
        echo "

  ";
        // line 58
        echo "  ";
        $this->displayBlock('content', $context, $blocks);
        // line 61
        echo "</nav>
";
    }

    // line 58
    public function block_content($context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 59
        echo "    ";
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["content"] ?? null), 59, $this->source), "html", null, true);
        echo "
  ";
    }

    public function getTemplateName()
    {
        return "core/profiles/demo_umami/themes/umami/templates/components/navigation/block--umami-main-menu.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  99 => 59,  95 => 58,  90 => 61,  87 => 58,  82 => 55,  76 => 54,  71 => 53,  68 => 52,  65 => 51,  62 => 50,  55 => 48,  53 => 47,  47 => 44,  43 => 42,  41 => 39,  40 => 35,);
    }

    public function getSourceContext()
    {
        return new Source("", "core/profiles/demo_umami/themes/umami/templates/components/navigation/block--umami-main-menu.html.twig", "/var/www/html/core/profiles/demo_umami/themes/umami/templates/components/navigation/block--umami-main-menu.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = array("set" => 35, "include" => 44, "if" => 50, "block" => 58);
        static $filters = array("clean_class" => 39, "clean_id" => 47, "escape" => 48, "without" => 48);
        static $functions = array("active_theme_path" => 44);

        try {
            $this->sandbox->checkSecurity(
                ['set', 'include', 'if', 'block'],
                ['clean_class', 'clean_id', 'escape', 'without'],
                ['active_theme_path']
            );
        } catch (SecurityError $e) {
            $e->setSourceContext($this->source);

            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            }

            throw $e;
        }

    }
}
