<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* core/profiles/demo_umami/themes/umami/templates/layout/page.html.twig */
class __TwigTemplate_2b423c14d07f4e2bccb65af463649c7d extends Template
{
    private Source $source;
    /**
     * @var array<string, Template>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->extensions[SandboxExtension::class];
        $this->checkSecurity();
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 45
        yield "<div class=\"layout-container\">

  ";
        // line 47
        if (( !Twig\Extension\CoreExtension::testEmpty(Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "pre_header", [], "any", false, false, true, 47)))) ||  !Twig\Extension\CoreExtension::testEmpty(Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source,         // line 48
($context["page"] ?? null), "header", [], "any", false, false, true, 48)))))) {
            // line 49
            yield "    <header class=\"layout-header\" role=\"banner\">
      <div class=\"container\">
        ";
            // line 51
            if ((($tmp =  !Twig\Extension\CoreExtension::testEmpty(Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "pre_header", [], "any", false, false, true, 51))))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                // line 52
                yield "          ";
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "pre_header", [], "any", false, false, true, 52), "html", null, true);
                yield "
        ";
            }
            // line 54
            yield "        ";
            if ((($tmp =  !Twig\Extension\CoreExtension::testEmpty(Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "header", [], "any", false, false, true, 54))))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                // line 55
                yield "          ";
                yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "header", [], "any", false, false, true, 55), "html", null, true);
                yield "
        ";
            }
            // line 57
            yield "      </div>
    </header>
  ";
        }
        // line 60
        yield "
  <main>

    ";
        // line 63
        if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "highlighted", [], "any", false, false, true, 63)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 64
            yield "      <div class=\"layout-highlighted\">
        <div class=\"container\">
          ";
            // line 66
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "highlighted", [], "any", false, false, true, 66), "html", null, true);
            yield "
        </div>
      </div>
    ";
        }
        // line 70
        yield "
    ";
        // line 71
        if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "tabs", [], "any", false, false, true, 71)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 72
            yield "    <div class=\"layout-tabs\">
      <div class=\"container\">
        ";
            // line 74
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "tabs", [], "any", false, false, true, 74), "html", null, true);
            yield "
      </div>
    </div>
    ";
        }
        // line 78
        yield "
    ";
        // line 79
        if ((($tmp =  !Twig\Extension\CoreExtension::testEmpty(Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "banner_top", [], "any", false, false, true, 79))))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 80
            yield "      <div class=\"layout-banner-top\">
        ";
            // line 81
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "banner_top", [], "any", false, false, true, 81), "html", null, true);
            yield "
      </div>
    ";
        }
        // line 84
        yield "
    ";
        // line 85
        if ((($tmp =  !Twig\Extension\CoreExtension::testEmpty(Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "breadcrumbs", [], "any", false, false, true, 85))))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 86
            yield "    <div class=\"layout-breadcrumbs\">
      <div class=\"container\">
        ";
            // line 88
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "breadcrumbs", [], "any", false, false, true, 88), "html", null, true);
            yield "
      </div>
    </div>
    ";
        }
        // line 92
        yield "
    ";
        // line 93
        if ((($tmp =  !($context["node"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 94
            yield "      ";
            if ((($tmp =  !Twig\Extension\CoreExtension::testEmpty(Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "page_title", [], "any", false, false, true, 94))))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                // line 95
                yield "        <div class=\"layout-page-title\">
          ";
                // line 96
                if ((($tmp = ($context["is_front"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
                    // line 97
                    yield "            <div class=\"is-front container\">
              ";
                    // line 98
                    yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "page_title", [], "any", false, false, true, 98), "html", null, true);
                    yield "
            </div>
            ";
                } else {
                    // line 101
                    yield "            <div class=\"container\">
              ";
                    // line 102
                    yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "page_title", [], "any", false, false, true, 102), "html", null, true);
                    yield "
            </div>
          ";
                }
                // line 105
                yield "        </div>
      ";
            }
            // line 107
            yield "    ";
        }
        // line 108
        yield "
    <div class=\"main-content-area container\">
      <div class=\"layout-content\">
        <a id=\"main-content\" tabindex=\"-1\"></a>";
        // line 112
        yield "        ";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "content", [], "any", false, false, true, 112), "html", null, true);
        yield "
      </div>";
        // line 114
        yield "
      ";
        // line 115
        if ((($tmp =  !Twig\Extension\CoreExtension::testEmpty(Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "sidebar", [], "any", false, false, true, 115))))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 116
            yield "        <aside class=\"layout-sidebar\" role=\"complementary\">
          ";
            // line 117
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "sidebar", [], "any", false, false, true, 117), "html", null, true);
            yield "
        </aside>
      ";
        }
        // line 120
        yield "    </div>

    ";
        // line 122
        if ((($tmp =  !Twig\Extension\CoreExtension::testEmpty(Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "content_bottom", [], "any", false, false, true, 122))))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 123
            yield "      <div class=\"layout-content-bottom\">
        ";
            // line 124
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "content_bottom", [], "any", false, false, true, 124), "html", null, true);
            yield "
      </div>
    ";
        }
        // line 127
        yield "  </main>

  <footer class=\"footer\" role=\"contentinfo\">
    ";
        // line 130
        if ((($tmp =  !Twig\Extension\CoreExtension::testEmpty(Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "footer", [], "any", false, false, true, 130))))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 131
            yield "    <div class=\"layout-footer\">
      <div class=\"container\">
        ";
            // line 133
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "footer", [], "any", false, false, true, 133), "html", null, true);
            yield "
      </div>
    </div>
    ";
        }
        // line 137
        yield "
    ";
        // line 138
        if ((($tmp =  !Twig\Extension\CoreExtension::testEmpty(Twig\Extension\CoreExtension::trim($this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "bottom", [], "any", false, false, true, 138))))) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 139
            yield "      <div class=\"layout-bottom\">
        <div class=\"container\">
          ";
            // line 141
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "bottom", [], "any", false, false, true, 141), "html", null, true);
            yield "
        </div>
      </div>
    ";
        }
        // line 145
        yield "  </footer>

</div>";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["page", "node", "is_front"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "core/profiles/demo_umami/themes/umami/templates/layout/page.html.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  252 => 145,  245 => 141,  241 => 139,  239 => 138,  236 => 137,  229 => 133,  225 => 131,  223 => 130,  218 => 127,  212 => 124,  209 => 123,  207 => 122,  203 => 120,  197 => 117,  194 => 116,  192 => 115,  189 => 114,  184 => 112,  179 => 108,  176 => 107,  172 => 105,  166 => 102,  163 => 101,  157 => 98,  154 => 97,  152 => 96,  149 => 95,  146 => 94,  144 => 93,  141 => 92,  134 => 88,  130 => 86,  128 => 85,  125 => 84,  119 => 81,  116 => 80,  114 => 79,  111 => 78,  104 => 74,  100 => 72,  98 => 71,  95 => 70,  88 => 66,  84 => 64,  82 => 63,  77 => 60,  72 => 57,  66 => 55,  63 => 54,  57 => 52,  55 => 51,  51 => 49,  49 => 48,  48 => 47,  44 => 45,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "core/profiles/demo_umami/themes/umami/templates/layout/page.html.twig", "/var/www/html/core/profiles/demo_umami/themes/umami/templates/layout/page.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 47];
        static $filters = ["trim" => 47, "render" => 47, "escape" => 52];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                ['if'],
                ['trim', 'render', 'escape'],
                [],
                $this->source
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
