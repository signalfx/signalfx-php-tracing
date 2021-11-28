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

/* core/profiles/demo_umami/themes/umami/templates/components/navigation/menu--main.html.twig */
class __TwigTemplate_26967cd0cf4312652a8af77aee14b86cca2f6b36110aaf7f2eb2ac62a7693a5f extends \Twig\Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->env->getExtension('\Twig\Extension\SandboxExtension');
        $this->checkSecurity();
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 21
        echo "
";
        // line 22
        $macros["menus"] = $this->macros["menus"] = $this;
        // line 23
        echo "
";
        // line 31
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(twig_call_macro($macros["menus"], "macro_menu_links", [($context["items"] ?? null), ($context["attributes"] ?? null), 0, ($context["menu_name"] ?? null)], 31, $context, $this->getSourceContext()));
        echo " ";
        // line 32
        echo "
";
    }

    // line 33
    public function macro_menu_links($__items__ = null, $__attributes__ = null, $__menu_level__ = null, $__menu_name__ = null, ...$__varargs__)
    {
        $macros = $this->macros;
        $context = $this->env->mergeGlobals([
            "items" => $__items__,
            "attributes" => $__attributes__,
            "menu_level" => $__menu_level__,
            "menu_name" => $__menu_name__,
            "varargs" => $__varargs__,
        ]);

        $blocks = [];

        ob_start(function () { return ''; });
        try {
            echo " ";
            // line 34
            echo "  ";
            $macros["menus"] = $this;
            // line 35
            echo "  ";
            // line 36
            echo "  ";
            // line 37
            $context["menu_classes"] = [0 => ("menu-" . \Drupal\Component\Utility\Html::getClass($this->sandbox->ensureToStringAllowed(            // line 38
($context["menu_name"] ?? null), 38, $this->source)))];
            // line 41
            echo "  ";
            // line 42
            echo "  ";
            // line 43
            $context["submenu_classes"] = [0 => (("menu-" . \Drupal\Component\Utility\Html::getClass($this->sandbox->ensureToStringAllowed(            // line 44
($context["menu_name"] ?? null), 44, $this->source))) . "__submenu")];
            // line 47
            echo "  ";
            if (($context["items"] ?? null)) {
                // line 48
                echo "    ";
                if ((($context["menu_level"] ?? null) == 0)) {
                    // line 49
                    echo "      <ul";
                    echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["attributes"] ?? null), "addClass", [0 => ($context["menu_classes"] ?? null)], "method", false, false, true, 49), "setAttribute", [0 => "data-drupal-selector", 1 => "menu-main"], "method", false, false, true, 49), 49, $this->source), "html", null, true);
                    echo "> ";
                    // line 50
                    echo "    ";
                } else {
                    // line 51
                    echo "      <ul";
                    echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, ($context["attributes"] ?? null), "removeClass", [0 => ($context["menu_classes"] ?? null)], "method", false, false, true, 51), "addClass", [0 => ($context["submenu_classes"] ?? null)], "method", false, false, true, 51), 51, $this->source), "html", null, true);
                    echo "> ";
                    // line 52
                    echo "    ";
                }
                // line 53
                echo "    ";
                $context['_parent'] = $context;
                $context['_seq'] = twig_ensure_traversable(($context["items"] ?? null));
                foreach ($context['_seq'] as $context["_key"] => $context["item"]) {
                    // line 54
                    echo "      ";
                    // line 55
                    echo "      ";
                    // line 56
                    $context["item_classes"] = [0 => (("menu-" . \Drupal\Component\Utility\Html::getClass($this->sandbox->ensureToStringAllowed(                    // line 57
($context["menu_name"] ?? null), 57, $this->source))) . "__item"), 1 => ((twig_get_attribute($this->env, $this->source,                     // line 58
$context["item"], "is_expanded", [], "any", false, false, true, 58)) ? ((("menu-" . \Drupal\Component\Utility\Html::getClass($this->sandbox->ensureToStringAllowed(($context["menu_name"] ?? null), 58, $this->source))) . "__item--expanded")) : ("")), 2 => ((twig_get_attribute($this->env, $this->source,                     // line 59
$context["item"], "is_collapsed", [], "any", false, false, true, 59)) ? ((("menu-" . \Drupal\Component\Utility\Html::getClass($this->sandbox->ensureToStringAllowed(($context["menu_name"] ?? null), 59, $this->source))) . "__item--collapsed")) : ("")), 3 => ((twig_get_attribute($this->env, $this->source,                     // line 60
$context["item"], "in_active_trail", [], "any", false, false, true, 60)) ? ((("menu-" . \Drupal\Component\Utility\Html::getClass($this->sandbox->ensureToStringAllowed(($context["menu_name"] ?? null), 60, $this->source))) . "__item--active-trail")) : (""))];
                    // line 63
                    echo "      ";
                    // line 64
                    echo "      ";
                    // line 65
                    $context["link_classes"] = [0 => (("menu-" . \Drupal\Component\Utility\Html::getClass($this->sandbox->ensureToStringAllowed(                    // line 66
($context["menu_name"] ?? null), 66, $this->source))) . "__link")];
                    // line 69
                    echo "      <li";
                    echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["item"], "attributes", [], "any", false, false, true, 69), "addClass", [0 => ($context["item_classes"] ?? null)], "method", false, false, true, 69), 69, $this->source), "html", null, true);
                    echo ">";
                    // line 70
                    echo "        ";
                    // line 71
                    echo "        ";
                    echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\Core\Template\TwigExtension']->getLink($this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source,                     // line 73
$context["item"], "title", [], "any", false, false, true, 73), 73, $this->source), $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source,                     // line 74
$context["item"], "url", [], "any", false, false, true, 74), 74, $this->source), $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source,                     // line 75
$context["item"], "attributes", [], "any", false, false, true, 75), "removeClass", [0 => ($context["item_classes"] ?? null)], "method", false, false, true, 75), "addClass", [0 => ($context["link_classes"] ?? null)], "method", false, false, true, 75), 75, $this->source)), "html", null, true);
                    // line 77
                    echo "
        ";
                    // line 78
                    if (twig_get_attribute($this->env, $this->source, $context["item"], "below", [], "any", false, false, true, 78)) {
                        // line 79
                        echo "          ";
                        echo $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(twig_call_macro($macros["menus"], "macro_menu_links", [twig_get_attribute($this->env, $this->source, $context["item"], "below", [], "any", false, false, true, 79), ($context["attributes"] ?? null), (($context["menu_level"] ?? null) + 1), ($context["menu_name"] ?? null)], 79, $context, $this->getSourceContext()));
                        echo " ";
                        // line 80
                        echo "        ";
                    }
                    // line 81
                    echo "      </li>
    ";
                }
                $_parent = $context['_parent'];
                unset($context['_seq'], $context['_iterated'], $context['_key'], $context['item'], $context['_parent'], $context['loop']);
                $context = array_intersect_key($context, $_parent) + $_parent;
                // line 83
                echo "    </ul>
  ";
            }

            return ('' === $tmp = ob_get_contents()) ? '' : new Markup($tmp, $this->env->getCharset());
        } finally {
            ob_end_clean();
        }
    }

    public function getTemplateName()
    {
        return "core/profiles/demo_umami/themes/umami/templates/components/navigation/menu--main.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  162 => 83,  155 => 81,  152 => 80,  148 => 79,  146 => 78,  143 => 77,  141 => 75,  140 => 74,  139 => 73,  137 => 71,  135 => 70,  131 => 69,  129 => 66,  128 => 65,  126 => 64,  124 => 63,  122 => 60,  121 => 59,  120 => 58,  119 => 57,  118 => 56,  116 => 55,  114 => 54,  109 => 53,  106 => 52,  102 => 51,  99 => 50,  95 => 49,  92 => 48,  89 => 47,  87 => 44,  86 => 43,  84 => 42,  82 => 41,  80 => 38,  79 => 37,  77 => 36,  75 => 35,  72 => 34,  55 => 33,  50 => 32,  47 => 31,  44 => 23,  42 => 22,  39 => 21,);
    }

    public function getSourceContext()
    {
        return new Source("", "core/profiles/demo_umami/themes/umami/templates/components/navigation/menu--main.html.twig", "/var/www/html/core/profiles/demo_umami/themes/umami/templates/components/navigation/menu--main.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = array("import" => 22, "macro" => 33, "set" => 37, "if" => 47, "for" => 53);
        static $filters = array("clean_class" => 38, "escape" => 49);
        static $functions = array("link" => 72);

        try {
            $this->sandbox->checkSecurity(
                ['import', 'macro', 'set', 'if', 'for'],
                ['clean_class', 'escape'],
                ['link']
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
