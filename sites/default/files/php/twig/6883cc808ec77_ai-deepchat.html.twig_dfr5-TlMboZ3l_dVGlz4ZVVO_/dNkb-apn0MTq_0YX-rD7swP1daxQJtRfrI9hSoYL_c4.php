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

/* modules/contrib/ai/modules/ai_chatbot/templates/ai-deepchat.html.twig */
class __TwigTemplate_8d1ee909fad6aef5f0cf621c206b2a49 extends Template
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
        // line 1
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\Core\Template\TwigExtension']->attachLibrary("ai_chatbot/deepchat"), "html", null, true);
        yield "
";
        // line 2
        if ((($context["current_theme"] ?? null) == "gin")) {
            // line 3
            yield "  ";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\Core\Template\TwigExtension']->attachLibrary("ai_chatbot/gin"), "html", null, true);
            yield "
";
        }
        // line 5
        yield "
<div class=\"";
        // line 6
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["current_theme"] ?? null), "html", null, true);
        yield " ai-deepchat chat-collapsed chat-container ";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["settings"] ?? null), "placement", [], "any", false, false, true, 6), "html", null, true);
        yield "\" data-chat-id=\"bot1\">

\t<div class=\"ai-deepchat--header\" tabindex=\"0\" role=\"button\" aria-label=\"Toggle Chat\" aria-expanded=\"false\">
    <div class=\"chat-dropdown\">
      <button type=\"button\" class=\"chat-dropdown-button\">
        <svg xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\" viewBox=\"0 0 24 24\" stroke-width=\"1.5\" stroke=\"currentColor\" id=\"chevron\" class=\"chevron-icon\">
          <path fill=\"none\" stroke=\"currentColor\" d=\"M3,6H21V8H3V6M3,11H21V13H3V11M3,16H21V18H3V16Z\" />
        </svg>
      </button>
      <div class=\"chat-dropdown-content\">
        <a class=\"clear-history chat-dropdown-link\">";
        // line 16
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Clear History"));
        yield "</a>
      </div>
    </div>

    <div class=\"ai-deepchat--label\">
\t\t\t<span class=\"ai-deepchat--bullet\"></span>
\t\t\t";
        // line 22
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["settings"] ?? null), "label", [], "any", false, false, true, 22), "html", null, true);
        yield "
\t\t</div>
\t\t<span class=\"toggle-icon\">
\t\t\t<svg xmlns=\"http://www.w3.org/2000/svg\" viewbox=\"-1 -1 16 16\" height=\"16\" width=\"16\">
\t\t\t\t<defs></defs>
\t\t\t\t<title>close</title>
\t\t\t\t<path d=\"m0.29166666666666663 0.2910833333333333 13.416666666666664 13.416666666666664\" fill=\"none\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\"></path>
\t\t\t\t<path d=\"m13.708333333333332 0.2910833333333333 -13.416666666666664 13.416666666666664\" fill=\"none\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\"></path>
\t\t\t</svg>
\t\t</span>
\t</div>

\t<div class=\"chat-element chat-collapsed\" style=\"min-width: ";
        // line 34
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["settings"] ?? null), "width", [], "any", false, false, true, 34), "html", null, true);
        yield "\">

\t\t<deep-chat ";
        // line 36
        $context['_parent'] = $context;
        $context['_seq'] = CoreExtension::ensureTraversable(($context["deepchat_settings"] ?? null));
        foreach ($context['_seq'] as $context["key"] => $context["value"]) {
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $context["key"], "html", null, true);
            yield "='";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($context["value"]);
            yield "'
    ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['key'], $context['value'], $context['_parent']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 37
        yield "></deep-chat>

\t</div>
</div>

";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["current_theme", "settings", "deepchat_settings"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "modules/contrib/ai/modules/ai_chatbot/templates/ai-deepchat.html.twig";
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
        return array (  116 => 37,  103 => 36,  98 => 34,  83 => 22,  74 => 16,  59 => 6,  56 => 5,  50 => 3,  48 => 2,  44 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "modules/contrib/ai/modules/ai_chatbot/templates/ai-deepchat.html.twig", "/var/www/html/modules/contrib/ai/modules/ai_chatbot/templates/ai-deepchat.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 2, "for" => 36];
        static $filters = ["escape" => 1, "t" => 16, "raw" => 36];
        static $functions = ["attach_library" => 1];

        try {
            $this->sandbox->checkSecurity(
                ['if', 'for'],
                ['escape', 't', 'raw'],
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
