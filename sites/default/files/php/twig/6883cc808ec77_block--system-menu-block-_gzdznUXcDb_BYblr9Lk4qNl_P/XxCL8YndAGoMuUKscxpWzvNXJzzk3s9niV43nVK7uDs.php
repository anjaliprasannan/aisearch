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

/* core/profiles/demo_umami/themes/umami/templates/components/navigation/block--system-menu-block--footer.html.twig */
class __TwigTemplate_8e6a6ce4be1641013477739f859f9184 extends Template
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
        // line 8
        $context["classes"] = ["block", ("block-" . \Drupal\Component\Utility\Html::getClass(CoreExtension::getAttribute($this->env, $this->source,         // line 10
($context["configuration"] ?? null), "provider", [], "any", false, false, true, 10))), ("block-" . \Drupal\Component\Utility\Html::getClass(        // line 11
($context["plugin_id"] ?? null)))];
        // line 14
        yield "
";
        // line 15
        yield from $this->load("core/profiles/demo_umami/themes/umami/templates/components/navigation/block--system-menu-block--footer.html.twig", 15, "380056631")->unwrap()->yield(CoreExtension::toArray(["attributes" => CoreExtension::getAttribute($this->env, $this->source,         // line 16
($context["attributes"] ?? null), "addClass", [($context["classes"] ?? null)], "method", false, false, true, 16), "content" =>         // line 17
($context["content"] ?? null), "label" =>         // line 18
($context["label"] ?? null), "title_attributes" =>         // line 19
($context["title_attributes"] ?? null), "title_prefix" =>         // line 20
($context["title_prefix"] ?? null), "title_suffix" =>         // line 21
($context["title_suffix"] ?? null)]));
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["configuration", "plugin_id", "attributes", "content", "label", "title_attributes", "title_prefix", "title_suffix"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "core/profiles/demo_umami/themes/umami/templates/components/navigation/block--system-menu-block--footer.html.twig";
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
        return array (  57 => 21,  56 => 20,  55 => 19,  54 => 18,  53 => 17,  52 => 16,  51 => 15,  48 => 14,  46 => 11,  45 => 10,  44 => 8,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "core/profiles/demo_umami/themes/umami/templates/components/navigation/block--system-menu-block--footer.html.twig", "/var/www/html/core/profiles/demo_umami/themes/umami/templates/components/navigation/block--system-menu-block--footer.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["set" => 8, "embed" => 15];
        static $filters = ["clean_class" => 10];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                ['set', 'embed'],
                ['clean_class'],
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


/* core/profiles/demo_umami/themes/umami/templates/components/navigation/block--system-menu-block--footer.html.twig */
class __TwigTemplate_8e6a6ce4be1641013477739f859f9184___380056631 extends Template
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
        // line 15
        return "umami:footer-block";
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        $this->parent = $this->load("umami:footer-block", 15);
        yield from $this->parent->unwrap()->yield($context, array_merge($this->blocks, $blocks));
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["title_attributes", "label", "content", "title_prefix", "title_suffix"]);    }

    // line 23
    /**
     * @return iterable<null|scalar|\Stringable>
     */
    public function block_content(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 24
        yield "    ";
        yield from $this->load("core/profiles/demo_umami/themes/umami/templates/components/navigation/block--system-menu-block--footer.html.twig", 24, "206583320")->unwrap()->yield(CoreExtension::toArray(["attributes" => CoreExtension::getAttribute($this->env, $this->source,         // line 25
($context["title_attributes"] ?? null), "addClass", ["footer-block__title"], "method", false, false, true, 25), "label" => (((($tmp =         // line 26
($context["label"] ?? null)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) ? (($context["label"] ?? null)) : (CoreExtension::getAttribute($this->env, $this->source, ($context["content"] ?? null), "field_title", [], "any", false, false, true, 26))), "title_prefix" =>         // line 27
($context["title_prefix"] ?? null), "title_suffix" =>         // line 28
($context["title_suffix"] ?? null)]));
        // line 37
        yield "    ";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["content"] ?? null), "html", null, true);
        yield "
  ";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "core/profiles/demo_umami/themes/umami/templates/components/navigation/block--system-menu-block--footer.html.twig";
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
        return array (  173 => 37,  171 => 28,  170 => 27,  169 => 26,  168 => 25,  166 => 24,  159 => 23,  147 => 15,  57 => 21,  56 => 20,  55 => 19,  54 => 18,  53 => 17,  52 => 16,  51 => 15,  48 => 14,  46 => 11,  45 => 10,  44 => 8,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "core/profiles/demo_umami/themes/umami/templates/components/navigation/block--system-menu-block--footer.html.twig", "/var/www/html/core/profiles/demo_umami/themes/umami/templates/components/navigation/block--system-menu-block--footer.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["extends" => 15, "embed" => 24];
        static $filters = ["escape" => 37];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                ['extends', 'embed'],
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


/* core/profiles/demo_umami/themes/umami/templates/components/navigation/block--system-menu-block--footer.html.twig */
class __TwigTemplate_8e6a6ce4be1641013477739f859f9184___206583320 extends Template
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
        // line 24
        return "umami:title";
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        $this->parent = $this->load("umami:title", 24);
        yield from $this->parent->unwrap()->yield($context, array_merge($this->blocks, $blocks));
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["title_prefix", "title_suffix"]);    }

    // line 30
    /**
     * @return iterable<null|scalar|\Stringable>
     */
    public function block_title_prefix(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 31
        yield "        ";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["title_prefix"] ?? null), "html", null, true);
        yield "
      ";
        yield from [];
    }

    // line 33
    /**
     * @return iterable<null|scalar|\Stringable>
     */
    public function block_title_suffix(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 34
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
        return "core/profiles/demo_umami/themes/umami/templates/components/navigation/block--system-menu-block--footer.html.twig";
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
        return array (  300 => 34,  293 => 33,  285 => 31,  278 => 30,  266 => 24,  173 => 37,  171 => 28,  170 => 27,  169 => 26,  168 => 25,  166 => 24,  159 => 23,  147 => 15,  57 => 21,  56 => 20,  55 => 19,  54 => 18,  53 => 17,  52 => 16,  51 => 15,  48 => 14,  46 => 11,  45 => 10,  44 => 8,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "core/profiles/demo_umami/themes/umami/templates/components/navigation/block--system-menu-block--footer.html.twig", "/var/www/html/core/profiles/demo_umami/themes/umami/templates/components/navigation/block--system-menu-block--footer.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["extends" => 24];
        static $filters = ["escape" => 31];
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
