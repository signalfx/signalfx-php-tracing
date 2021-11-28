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

/* core/modules/system/templates/system-themes-page.html.twig */
class __TwigTemplate_57fb72bed2b12eb2453b144f42f6384e83acd24e98279c79cd64bd783eb27552 extends \Twig\Template
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
        // line 34
        echo "<div";
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(($context["attributes"] ?? null), 34, $this->source), "html", null, true);
        echo ">
  ";
        // line 35
        $context['_parent'] = $context;
        $context['_seq'] = twig_ensure_traversable(($context["theme_groups"] ?? null));
        foreach ($context['_seq'] as $context["_key"] => $context["theme_group"]) {
            // line 36
            echo "    ";
            // line 37
            $context["theme_group_classes"] = [0 => "system-themes-list", 1 => ("system-themes-list-" . $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source,             // line 39
$context["theme_group"], "state", [], "any", false, false, true, 39), 39, $this->source)), 2 => "clearfix"];
            // line 43
            echo "    <div";
            echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["theme_group"], "attributes", [], "any", false, false, true, 43), "addClass", [0 => ($context["theme_group_classes"] ?? null)], "method", false, false, true, 43), 43, $this->source), "html", null, true);
            echo ">
      <h2 class=\"system-themes-list__header\">";
            // line 44
            echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, $context["theme_group"], "title", [], "any", false, false, true, 44), 44, $this->source), "html", null, true);
            echo "</h2>
      ";
            // line 45
            $context['_parent'] = $context;
            $context['_seq'] = twig_ensure_traversable(twig_get_attribute($this->env, $this->source, $context["theme_group"], "themes", [], "any", false, false, true, 45));
            foreach ($context['_seq'] as $context["_key"] => $context["theme"]) {
                // line 46
                echo "        ";
                // line 47
                $context["theme_classes"] = [0 => ((twig_get_attribute($this->env, $this->source,                 // line 48
$context["theme"], "is_default", [], "any", false, false, true, 48)) ? ("theme-default") : ("")), 1 => ((twig_get_attribute($this->env, $this->source,                 // line 49
$context["theme"], "is_admin", [], "any", false, false, true, 49)) ? ("theme-admin") : ("")), 2 => "theme-selector", 3 => "clearfix"];
                // line 54
                echo "        <div";
                echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, twig_get_attribute($this->env, $this->source, $context["theme"], "attributes", [], "any", false, false, true, 54), "addClass", [0 => ($context["theme_classes"] ?? null)], "method", false, false, true, 54), 54, $this->source), "html", null, true);
                echo ">
          ";
                // line 55
                if (twig_get_attribute($this->env, $this->source, $context["theme"], "screenshot", [], "any", false, false, true, 55)) {
                    // line 56
                    echo "            ";
                    echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, $context["theme"], "screenshot", [], "any", false, false, true, 56), 56, $this->source), "html", null, true);
                    echo "
          ";
                }
                // line 58
                echo "          <div class=\"theme-info\">
            <h3 class=\"theme-info__header\">";
                // line 60
                echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, $context["theme"], "name", [], "any", false, false, true, 60), 60, $this->source), "html", null, true);
                echo " ";
                echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, $context["theme"], "version", [], "any", false, false, true, 60), 60, $this->source), "html", null, true);
                // line 61
                if (twig_get_attribute($this->env, $this->source, $context["theme"], "notes", [], "any", false, false, true, 61)) {
                    // line 62
                    echo "                (";
                    echo $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\Core\Template\TwigExtension']->safeJoin($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, $context["theme"], "notes", [], "any", false, false, true, 62), 62, $this->source), ", "));
                    echo ")";
                }
                // line 64
                echo "</h3>
            <div class=\"theme-info__description\">";
                // line 65
                echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, $context["theme"], "description", [], "any", false, false, true, 65), 65, $this->source), "html", null, true);
                echo "</div>
            ";
                // line 66
                if (twig_get_attribute($this->env, $this->source, $context["theme"], "module_dependencies", [], "any", false, false, true, 66)) {
                    // line 67
                    echo "              <div class=\"theme-info__requires\">
                ";
                    // line 68
                    echo $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Requires: @module_dependencies", ["@module_dependencies" => $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, $context["theme"], "module_dependencies", [], "any", false, false, true, 68), 68, $this->source))]));
                    echo "
              </div>
            ";
                }
                // line 71
                echo "            ";
                // line 72
                echo "            ";
                if (twig_get_attribute($this->env, $this->source, $context["theme"], "incompatible", [], "any", false, false, true, 72)) {
                    // line 73
                    echo "              <div class=\"incompatible\">";
                    echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, $context["theme"], "incompatible", [], "any", false, false, true, 73), 73, $this->source), "html", null, true);
                    echo "</div>
            ";
                } else {
                    // line 75
                    echo "              ";
                    echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, $context["theme"], "operations", [], "any", false, false, true, 75), 75, $this->source), "html", null, true);
                    echo "
            ";
                }
                // line 77
                echo "          </div>
        </div>
      ";
            }
            $_parent = $context['_parent'];
            unset($context['_seq'], $context['_iterated'], $context['_key'], $context['theme'], $context['_parent'], $context['loop']);
            $context = array_intersect_key($context, $_parent) + $_parent;
            // line 80
            echo "    </div>
  ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_iterated'], $context['_key'], $context['theme_group'], $context['_parent'], $context['loop']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 82
        echo "</div>
";
    }

    public function getTemplateName()
    {
        return "core/modules/system/templates/system-themes-page.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  149 => 82,  142 => 80,  134 => 77,  128 => 75,  122 => 73,  119 => 72,  117 => 71,  111 => 68,  108 => 67,  106 => 66,  102 => 65,  99 => 64,  94 => 62,  92 => 61,  88 => 60,  85 => 58,  79 => 56,  77 => 55,  72 => 54,  70 => 49,  69 => 48,  68 => 47,  66 => 46,  62 => 45,  58 => 44,  53 => 43,  51 => 39,  50 => 37,  48 => 36,  44 => 35,  39 => 34,);
    }

    public function getSourceContext()
    {
        return new Source("", "core/modules/system/templates/system-themes-page.html.twig", "/var/www/html/core/modules/system/templates/system-themes-page.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = array("for" => 35, "set" => 37, "if" => 55);
        static $filters = array("escape" => 34, "safe_join" => 62, "t" => 68, "render" => 68);
        static $functions = array();

        try {
            $this->sandbox->checkSecurity(
                ['for', 'set', 'if'],
                ['escape', 'safe_join', 't', 'render'],
                []
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
