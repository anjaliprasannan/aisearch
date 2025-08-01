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

/* core/profiles/demo_umami/themes/umami/images/svg/search.svg */
class __TwigTemplate_269b722b1e2a793606ec18c3f7f2dfd3 extends Template
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
        yield "<svg width=\"16px\" height=\"16px\" viewBox=\"0 0 16 16\" version=\"1.1\" xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\"><g id=\"Page-1\" stroke=\"none\" stroke-width=\"1\" fill=\"none\" fill-rule=\"evenodd\"><g id=\"Group\"><g id=\"search\"><path d=\"M5.40705882,9.76579186 L1.13049774,14.0423529 L1.95800905,14.8691403 L6.23384615,10.5925792 C5.93199325,10.3445547 5.65508327,10.0676448 5.40705882,9.76579186 L5.40705882,9.76579186 Z M10.0180995,1.21013575 C7.38262671,1.21013575 5.24615385,3.34660861 5.24615385,5.98208145 C5.24615385,8.61755429 7.38262671,10.7540271 10.0180995,10.7540271 C12.6534724,10.7540271 14.7898643,8.61763532 14.7898643,5.98226244 C14.7898643,3.34688957 12.6534724,1.21049774 10.0180995,1.21049774 L10.0180995,1.21013575 Z M6.91113122,5.46932127 C6.65303181,5.46932127 6.4438009,5.26009036 6.4438009,5.00199095 C6.4438009,4.74389154 6.65303181,4.53466063 6.91113122,4.53466063 C7.16923063,4.53466063 7.37846154,4.74389154 7.37846154,5.00199095 C7.37846154,5.12593466 7.32922509,5.24480195 7.24158366,5.33244339 C7.15394222,5.42008482 7.03507493,5.46932127 6.91113122,5.46932127 Z M7.94027149,4.33628959 C7.48843702,4.33549024 7.12272582,3.96869932 7.12325849,3.51686445 C7.12379115,3.06502958 7.49036615,2.69910196 7.94220126,2.69936794 C8.39403636,2.69963392 8.76018029,3.06599287 8.760181,3.51782805 C8.76018134,3.73514866 8.67375208,3.94354729 8.51994746,4.09708029 C8.36614283,4.2506133 8.15759176,4.33667406 7.94027149,4.33628959 Z M9.59276018,3.21158371 C9.3345954,3.21018605 9.12631234,3.00001615 9.12724286,2.74184926 C9.12817338,2.48368237 9.33796605,2.27501936 9.59613419,2.27548273 C9.85430234,2.27594609 10.0633446,2.48536085 10.0633484,2.74352941 C10.0633502,2.86810541 10.0136898,2.98754248 9.92536382,3.07539289 C9.83703781,3.16324331 9.71733436,3.21225814 9.59276018,3.21158371 L9.59276018,3.21158371 Z\"></path><path d=\"M10.0180995,0.0170135747 C7.91074463,0.0175014928 5.95997609,1.12971952 4.88615078,2.94296087 C3.81232547,4.75620223 3.7747616,7.00144515 4.78733032,8.84959276 C4.63937697,8.9190674 4.50463812,9.01375207 4.38914027,9.12941176 L0.430769231,13.0888688 C-0.12045472,13.6401793 -0.12045472,14.5339384 0.430769231,15.0852489 L0.914751131,15.5692308 C1.1795314,15.8341616 1.53874087,15.9830108 1.91330317,15.9830108 C2.28786547,15.9830108 2.64707494,15.8341616 2.9118552,15.5692308 L6.86950226,11.6112217 C6.98533647,11.495791 7.08015077,11.361042 7.14968326,11.2130317 C9.3343265,12.4100266 12.0327729,12.1233591 13.9171704,10.4940926 C15.8015678,8.86482619 16.4750474,6.23609712 15.606203,3.90145101 C14.7373587,1.56680491 12.509176,0.0179366079 10.0180995,0.0170135747 L10.0180995,0.0170135747 Z M1.95800905,14.8691403 L1.13049774,14.0423529 L5.40705882,9.76579186 C5.65508327,10.0676448 5.93199325,10.3445547 6.23384615,10.5925792 L1.95800905,14.8691403 Z M10.0180995,10.7536652 C7.38272668,10.7538651 5.2461728,8.61763532 5.24597288,5.98226245 C5.24577296,3.34688959 7.38200271,1.2103357 10.0173756,1.21013577 C12.6527484,1.20993585 14.7893023,3.3461656 14.7895023,5.98153846 C14.7897904,7.24736947 14.2870686,8.46143806 13.3919909,9.35651577 C12.4969132,10.2515935 11.2828446,10.7543153 10.0170136,10.7540271 L10.0180995,10.7536652 Z\" fill=\"#D93760\" fill-rule=\"nonzero\"></path><circle fill=\"#D93760\" fill-rule=\"nonzero\" cx=\"7.94027149\" cy=\"3.51782805\" r=\"1\"></circle><circle fill=\"#D93760\" fill-rule=\"nonzero\" cx=\"9.59457014\" cy=\"2.74352941\" r=\"1\"></circle><circle fill=\"#D93760\" fill-rule=\"nonzero\" cx=\"6.91113122\" cy=\"5.00199095\" r=\"1\"></circle></g></g></g></svg>
";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "core/profiles/demo_umami/themes/umami/images/svg/search.svg";
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  44 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "core/profiles/demo_umami/themes/umami/images/svg/search.svg", "/var/www/html/core/profiles/demo_umami/themes/umami/images/svg/search.svg");
    }
    
    public function checkSecurity()
    {
        static $tags = [];
        static $filters = [];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                [],
                [],
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
