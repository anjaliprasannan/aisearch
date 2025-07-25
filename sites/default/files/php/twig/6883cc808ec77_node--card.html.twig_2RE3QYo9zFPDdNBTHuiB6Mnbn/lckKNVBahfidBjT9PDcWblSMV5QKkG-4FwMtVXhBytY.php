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

/* core/profiles/demo_umami/themes/umami/templates/content/node--card.html.twig */
class __TwigTemplate_ffcd5f5fa20dae3018d1b563005fab07 extends Template
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
        // line 63
        $context["classes"] = ["node", ("node--type-" . \Drupal\Component\Utility\Html::getClass(CoreExtension::getAttribute($this->env, $this->source,         // line 65
($context["node"] ?? null), "bundle", [], "any", false, false, true, 65))), (((($tmp = CoreExtension::getAttribute($this->env, $this->source,         // line 66
($context["node"] ?? null), "isPromoted", [], "method", false, false, true, 66)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? ("node--promoted") : ("")), (((($tmp = CoreExtension::getAttribute($this->env, $this->source,         // line 67
($context["node"] ?? null), "isSticky", [], "method", false, false, true, 67)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? ("node--sticky") : ("")), (((($tmp =  !CoreExtension::getAttribute($this->env, $this->source,         // line 68
($context["node"] ?? null), "isPublished", [], "method", false, false, true, 68)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? ("node--unpublished") : ("")), (((($tmp =         // line 69
($context["view_mode"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? (("node--view-mode-" . \Drupal\Component\Utility\Html::getClass(($context["view_mode"] ?? null)))) : (""))];
        // line 72
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\Core\Template\TwigExtension']->attachLibrary("umami/classy.node"), "html", null, true);
        yield "

";
        // line 74
        $context["read_more"] = ('' === $tmp = \Twig\Extension\CoreExtension::captureOutput((function () use (&$context, $macros, $blocks) {
            // line 75
            yield t("View @node.type.entity.label", ["@node.type.entity.label" => CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, CoreExtension::getAttribute($this->env, $this->source, ($context["node"] ?? null), "type", [], "any", false, false, true, 75), "entity", [], "any", false, false, true, 75), "label", [], "method", false, false, true, 75), ]);
            yield from [];
        })())) ? '' : new Markup($tmp, $this->env->getCharset());
        // line 77
        yield "
";
        // line 78
        yield from $this->load("core/profiles/demo_umami/themes/umami/templates/content/node--card.html.twig", 78, "1894725249")->unwrap()->yield(CoreExtension::toArray(["attributes" => CoreExtension::getAttribute($this->env, $this->source,         // line 79
($context["attributes"] ?? null), "addClass", [($context["classes"] ?? null)], "method", false, false, true, 79), "content" =>         // line 80
($context["content"] ?? null), "content_attributes" =>         // line 81
($context["content_attributes"] ?? null), "label" =>         // line 82
($context["label"] ?? null), "title_attributes" =>         // line 83
($context["title_attributes"] ?? null), "title_prefix" =>         // line 84
($context["title_prefix"] ?? null), "title_suffix" =>         // line 85
($context["title_suffix"] ?? null), "read_more" =>         // line 86
($context["read_more"] ?? null), "url" =>         // line 87
($context["url"] ?? null)]));
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["node", "view_mode", "attributes", "content", "content_attributes", "label", "title_attributes", "title_prefix", "title_suffix", "url"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "core/profiles/demo_umami/themes/umami/templates/content/node--card.html.twig";
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
        return array (  74 => 87,  73 => 86,  72 => 85,  71 => 84,  70 => 83,  69 => 82,  68 => 81,  67 => 80,  66 => 79,  65 => 78,  62 => 77,  58 => 75,  56 => 74,  51 => 72,  49 => 69,  48 => 68,  47 => 67,  46 => 66,  45 => 65,  44 => 63,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "core/profiles/demo_umami/themes/umami/templates/content/node--card.html.twig", "/var/www/html/core/profiles/demo_umami/themes/umami/templates/content/node--card.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["set" => 63, "trans" => 75, "embed" => 78];
        static $filters = ["clean_class" => 65, "escape" => 72];
        static $functions = ["attach_library" => 72];

        try {
            $this->sandbox->checkSecurity(
                ['set', 'trans', 'embed'],
                ['clean_class', 'escape'],
                ['attach_library'],
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


/* core/profiles/demo_umami/themes/umami/templates/content/node--card.html.twig */
class __TwigTemplate_ffcd5f5fa20dae3018d1b563005fab07___1894725249 extends Template
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

        $this->blocks = [
            'content' => [$this, 'block_content'],
        ];
        $this->sandbox = $this->extensions[SandboxExtension::class];
        $this->checkSecurity();
    }

    protected function doGetParent(array $context): bool|string|Template|TemplateWrapper
    {
        // line 78
        return "umami:card";
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        $this->parent = $this->load("umami:card", 78);
        yield from $this->parent->unwrap()->yield($context, array_merge($this->blocks, $blocks));
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["title_attributes", "label", "content", "title_prefix", "title_suffix", "content_attributes", "read_more", "url"]);    }

    // line 89
    /**
     * @return iterable<null|scalar|\Stringable>
     */
    public function block_content(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 90
        yield "    ";
        yield from $this->load("core/profiles/demo_umami/themes/umami/templates/content/node--card.html.twig", 90, "698302323")->unwrap()->yield(CoreExtension::toArray(["attributes" => CoreExtension::getAttribute($this->env, $this->source,         // line 91
($context["title_attributes"] ?? null), "addClass", ["umami-card__title"], "method", false, false, true, 91), "label" => (((($tmp =         // line 92
($context["label"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? (($context["label"] ?? null)) : (CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_title", [], "any", false, false, true, 92))), "title_prefix" =>         // line 93
($context["title_prefix"] ?? null), "title_suffix" =>         // line 94
($context["title_suffix"] ?? null)]));
        // line 103
        yield "    <div";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["content_attributes"] ?? null), "addClass", ["umami-card__content"], "method", false, false, true, 103), "html", null, true);
        yield ">
      ";
        // line 104
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["content"] ?? null), "html", null, true);
        yield "
    </div>
    ";
        // line 106
        yield from $this->load("core/profiles/demo_umami/themes/umami/templates/content/node--card.html.twig", 106, "1027894878")->unwrap()->yield(CoreExtension::toArray(["attributes" => $this->extensions['Drupal\Core\Template\TwigExtension']->createAttribute(["class" => ["umami-card__read-more"]]), "read_more" =>         // line 108
($context["read_more"] ?? null), "label" =>         // line 109
($context["label"] ?? null), "url" =>         // line 110
($context["url"] ?? null)]));
        // line 119
        yield "  ";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "core/profiles/demo_umami/themes/umami/templates/content/node--card.html.twig";
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
        return array (  205 => 119,  203 => 110,  202 => 109,  201 => 108,  200 => 106,  195 => 104,  190 => 103,  188 => 94,  187 => 93,  186 => 92,  185 => 91,  183 => 90,  176 => 89,  164 => 78,  74 => 87,  73 => 86,  72 => 85,  71 => 84,  70 => 83,  69 => 82,  68 => 81,  67 => 80,  66 => 79,  65 => 78,  62 => 77,  58 => 75,  56 => 74,  51 => 72,  49 => 69,  48 => 68,  47 => 67,  46 => 66,  45 => 65,  44 => 63,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "core/profiles/demo_umami/themes/umami/templates/content/node--card.html.twig", "/var/www/html/core/profiles/demo_umami/themes/umami/templates/content/node--card.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["extends" => 78, "embed" => 90];
        static $filters = ["escape" => 103];
        static $functions = ["create_attribute" => 107];

        try {
            $this->sandbox->checkSecurity(
                ['extends', 'embed'],
                ['escape'],
                ['create_attribute'],
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


/* core/profiles/demo_umami/themes/umami/templates/content/node--card.html.twig */
class __TwigTemplate_ffcd5f5fa20dae3018d1b563005fab07___698302323 extends Template
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

        $this->blocks = [
            'title_prefix' => [$this, 'block_title_prefix'],
            'title_suffix' => [$this, 'block_title_suffix'],
        ];
        $this->sandbox = $this->extensions[SandboxExtension::class];
        $this->checkSecurity();
    }

    protected function doGetParent(array $context): bool|string|Template|TemplateWrapper
    {
        // line 90
        return "umami:title";
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        $this->parent = $this->load("umami:title", 90);
        yield from $this->parent->unwrap()->yield($context, array_merge($this->blocks, $blocks));
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["title_prefix", "title_suffix"]);    }

    // line 96
    /**
     * @return iterable<null|scalar|\Stringable>
     */
    public function block_title_prefix(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 97
        yield "        ";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["title_prefix"] ?? null), "html", null, true);
        yield "
      ";
        yield from [];
    }

    // line 99
    /**
     * @return iterable<null|scalar|\Stringable>
     */
    public function block_title_suffix(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 100
        yield "        ";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["title_suffix"] ?? null), "html", null, true);
        yield "
      ";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "core/profiles/demo_umami/themes/umami/templates/content/node--card.html.twig";
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
        return array (  329 => 100,  322 => 99,  314 => 97,  307 => 96,  295 => 90,  205 => 119,  203 => 110,  202 => 109,  201 => 108,  200 => 106,  195 => 104,  190 => 103,  188 => 94,  187 => 93,  186 => 92,  185 => 91,  183 => 90,  176 => 89,  164 => 78,  74 => 87,  73 => 86,  72 => 85,  71 => 84,  70 => 83,  69 => 82,  68 => 81,  67 => 80,  66 => 79,  65 => 78,  62 => 77,  58 => 75,  56 => 74,  51 => 72,  49 => 69,  48 => 68,  47 => 67,  46 => 66,  45 => 65,  44 => 63,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "core/profiles/demo_umami/themes/umami/templates/content/node--card.html.twig", "/var/www/html/core/profiles/demo_umami/themes/umami/templates/content/node--card.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["extends" => 90];
        static $filters = ["escape" => 97];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                ['extends'],
                ['escape'],
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


/* core/profiles/demo_umami/themes/umami/templates/content/node--card.html.twig */
class __TwigTemplate_ffcd5f5fa20dae3018d1b563005fab07___1027894878 extends Template
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

        $this->blocks = [
            'a11y' => [$this, 'block_a11y'],
            'text' => [$this, 'block_text'],
        ];
        $this->sandbox = $this->extensions[SandboxExtension::class];
        $this->checkSecurity();
    }

    protected function doGetParent(array $context): bool|string|Template|TemplateWrapper
    {
        // line 106
        return "umami:read-more";
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        $this->parent = $this->load("umami:read-more", 106);
        yield from $this->parent->unwrap()->yield($context, array_merge($this->blocks, $blocks));
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["label", "read_more"]);    }

    // line 112
    /**
     * @return iterable<null|scalar|\Stringable>
     */
    public function block_a11y(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 113
        yield "        ";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["label"] ?? null), "html", null, true);
        yield "
      ";
        yield from [];
    }

    // line 115
    /**
     * @return iterable<null|scalar|\Stringable>
     */
    public function block_text(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 116
        yield "        ";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["read_more"] ?? null), "html", null, true);
        yield "
      ";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "core/profiles/demo_umami/themes/umami/templates/content/node--card.html.twig";
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
        return array (  456 => 116,  449 => 115,  441 => 113,  434 => 112,  422 => 106,  329 => 100,  322 => 99,  314 => 97,  307 => 96,  295 => 90,  205 => 119,  203 => 110,  202 => 109,  201 => 108,  200 => 106,  195 => 104,  190 => 103,  188 => 94,  187 => 93,  186 => 92,  185 => 91,  183 => 90,  176 => 89,  164 => 78,  74 => 87,  73 => 86,  72 => 85,  71 => 84,  70 => 83,  69 => 82,  68 => 81,  67 => 80,  66 => 79,  65 => 78,  62 => 77,  58 => 75,  56 => 74,  51 => 72,  49 => 69,  48 => 68,  47 => 67,  46 => 66,  45 => 65,  44 => 63,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "core/profiles/demo_umami/themes/umami/templates/content/node--card.html.twig", "/var/www/html/core/profiles/demo_umami/themes/umami/templates/content/node--card.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["extends" => 106];
        static $filters = ["escape" => 113];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                ['extends'],
                ['escape'],
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
