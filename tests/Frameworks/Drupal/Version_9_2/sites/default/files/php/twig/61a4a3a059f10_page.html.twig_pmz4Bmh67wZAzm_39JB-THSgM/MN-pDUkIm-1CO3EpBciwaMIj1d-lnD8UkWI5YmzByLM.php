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

/* core/profiles/demo_umami/themes/umami/templates/layout/page.html.twig */
class __TwigTemplate_02c1bd7bf8b8de420248f3b8ab473dae4e6cec5958a28a298de5f7e033ae4f16 extends \Twig\Template
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
        // line 45
        echo "<div class=\"layout-container\">

  ";
        // line 47
        if (( !twig_test_empty(twig_trim_filter(strip_tags($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "pre_header", [], "any", false, false, true, 47))))) ||  !twig_test_empty(twig_trim_filter(strip_tags($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(twig_get_attribute($this->env, $this->source,         // line 48
($context["page"] ?? null), "header", [], "any", false, false, true, 48))))))) {
            // line 49
            echo "    <header class=\"layout-header\" role=\"banner\">
      <div class=\"container\">
        ";
            // line 51
            if ( !twig_test_empty(twig_trim_filter(strip_tags($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "pre_header", [], "any", false, false, true, 51)))))) {
                // line 52
                echo "          ";
                echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "pre_header", [], "any", false, false, true, 52), 52, $this->source), "html", null, true);
                echo "
        ";
            }
            // line 54
            echo "        ";
            if ( !twig_test_empty(twig_trim_filter(strip_tags($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "header", [], "any", false, false, true, 54)))))) {
                // line 55
                echo "          ";
                echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "header", [], "any", false, false, true, 55), 55, $this->source), "html", null, true);
                echo "
        ";
            }
            // line 57
            echo "      </div>
    </header>
  ";
        }
        // line 60
        echo "
  ";
        // line 61
        if (twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "highlighted", [], "any", false, false, true, 61)) {
            // line 62
            echo "    <div class=\"layout-highlighted\">
      <div class=\"container\">
        ";
            // line 64
            echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "highlighted", [], "any", false, false, true, 64), 64, $this->source), "html", null, true);
            echo "
      </div>
    </div>
  ";
        }
        // line 68
        echo "
  ";
        // line 69
        if (twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "tabs", [], "any", false, false, true, 69)) {
            // line 70
            echo "  <div class=\"layout-tabs\">
    <div class=\"container\">
      ";
            // line 72
            echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "tabs", [], "any", false, false, true, 72), 72, $this->source), "html", null, true);
            echo "
    </div>
  </div>
  ";
        }
        // line 76
        echo "
  ";
        // line 77
        if ( !twig_test_empty(twig_trim_filter(strip_tags($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "banner_top", [], "any", false, false, true, 77)))))) {
            // line 78
            echo "    <div class=\"layout-banner-top\">
      ";
            // line 79
            echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "banner_top", [], "any", false, false, true, 79), 79, $this->source), "html", null, true);
            echo "
    </div>
  ";
        }
        // line 82
        echo "
  ";
        // line 83
        if ( !twig_test_empty(twig_trim_filter(strip_tags($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "breadcrumbs", [], "any", false, false, true, 83)))))) {
            // line 84
            echo "  <div class=\"layout-breadcrumbs\">
    <div class=\"container\">
      ";
            // line 86
            echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "breadcrumbs", [], "any", false, false, true, 86), 86, $this->source), "html", null, true);
            echo "
    </div>
  </div>
  ";
        }
        // line 90
        echo "
  ";
        // line 91
        if ( !($context["node"] ?? null)) {
            // line 92
            echo "    ";
            if ( !twig_test_empty(twig_trim_filter(strip_tags($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "page_title", [], "any", false, false, true, 92)))))) {
                // line 93
                echo "      <div class=\"layout-page-title\">
        ";
                // line 94
                if (($context["is_front"] ?? null)) {
                    // line 95
                    echo "          <div class=\"is-front container\">
            ";
                    // line 96
                    echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "page_title", [], "any", false, false, true, 96), 96, $this->source), "html", null, true);
                    echo "
          </div>
          ";
                } else {
                    // line 99
                    echo "          <div class=\"container\">
            ";
                    // line 100
                    echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "page_title", [], "any", false, false, true, 100), 100, $this->source), "html", null, true);
                    echo "
          </div>
        ";
                }
                // line 103
                echo "      </div>
    ";
            }
            // line 105
            echo "  ";
        }
        // line 106
        echo "
  <main role=\"main\" class=\"main container\">

    <div class=\"layout-content\">
      <a id=\"main-content\" tabindex=\"-1\"></a>";
        // line 111
        echo "      ";
        echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "content", [], "any", false, false, true, 111), 111, $this->source), "html", null, true);
        echo "
    </div>";
        // line 113
        echo "
    ";
        // line 114
        if ( !twig_test_empty(twig_trim_filter(strip_tags($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "sidebar", [], "any", false, false, true, 114)))))) {
            // line 115
            echo "      <aside class=\"layout-sidebar\" role=\"complementary\">
        ";
            // line 116
            echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "sidebar", [], "any", false, false, true, 116), 116, $this->source), "html", null, true);
            echo "
      </aside>
    ";
        }
        // line 119
        echo "
  </main>

  ";
        // line 122
        if ( !twig_test_empty(twig_trim_filter(strip_tags($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "content_bottom", [], "any", false, false, true, 122)))))) {
            // line 123
            echo "    <div class=\"layout-content-bottom\">
      ";
            // line 124
            echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "content_bottom", [], "any", false, false, true, 124), 124, $this->source), "html", null, true);
            echo "
    </div>
  ";
        }
        // line 127
        echo "
  ";
        // line 128
        if ( !twig_test_empty(twig_trim_filter(strip_tags($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "footer", [], "any", false, false, true, 128)))))) {
            // line 129
            echo "  <div class=\"layout-footer\">
    <footer class=\"footer\" role=\"contentinfo\">
      <div class=\"container\">
        ";
            // line 132
            echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "footer", [], "any", false, false, true, 132), 132, $this->source), "html", null, true);
            echo "
      </div>
    </footer>
  </div>
  ";
        }
        // line 137
        echo "
  ";
        // line 138
        if ( !twig_test_empty(twig_trim_filter(strip_tags($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "bottom", [], "any", false, false, true, 138)))))) {
            // line 139
            echo "    <div class=\"layout-bottom\">
      <div class=\"container\">
        ";
            // line 141
            echo $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->sandbox->ensureToStringAllowed(twig_get_attribute($this->env, $this->source, ($context["page"] ?? null), "bottom", [], "any", false, false, true, 141), 141, $this->source), "html", null, true);
            echo "
      </div>
    </div>
  ";
        }
        // line 145
        echo "
</div>";
    }

    public function getTemplateName()
    {
        return "core/profiles/demo_umami/themes/umami/templates/layout/page.html.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  247 => 145,  240 => 141,  236 => 139,  234 => 138,  231 => 137,  223 => 132,  218 => 129,  216 => 128,  213 => 127,  207 => 124,  204 => 123,  202 => 122,  197 => 119,  191 => 116,  188 => 115,  186 => 114,  183 => 113,  178 => 111,  172 => 106,  169 => 105,  165 => 103,  159 => 100,  156 => 99,  150 => 96,  147 => 95,  145 => 94,  142 => 93,  139 => 92,  137 => 91,  134 => 90,  127 => 86,  123 => 84,  121 => 83,  118 => 82,  112 => 79,  109 => 78,  107 => 77,  104 => 76,  97 => 72,  93 => 70,  91 => 69,  88 => 68,  81 => 64,  77 => 62,  75 => 61,  72 => 60,  67 => 57,  61 => 55,  58 => 54,  52 => 52,  50 => 51,  46 => 49,  44 => 48,  43 => 47,  39 => 45,);
    }

    public function getSourceContext()
    {
        return new Source("", "core/profiles/demo_umami/themes/umami/templates/layout/page.html.twig", "/home/circleci/app/tests/Frameworks/Drupal/drupal-9.2.10/core/profiles/demo_umami/themes/umami/templates/layout/page.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = array("if" => 47);
        static $filters = array("trim" => 47, "striptags" => 47, "render" => 47, "escape" => 52);
        static $functions = array();

        try {
            $this->sandbox->checkSecurity(
                ['if'],
                ['trim', 'striptags', 'render', 'escape'],
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
