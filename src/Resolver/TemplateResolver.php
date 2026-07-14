<?php

declare(strict_types=1);

namespace App\Resolver;

use App\Attributes\Template as TemplateAttribute;
use Modufolio\Appkit\Resolver\AttributeResolverInterface;
use Modufolio\Appkit\Template\Template;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves a controller parameter carrying the {@see TemplateAttribute} into a
 * configured {@see Template} instance, injecting the view/layout paths and the
 * current request so controllers no longer repeat that wiring by hand.
 */
final class TemplateResolver implements AttributeResolverInterface
{
    /**
     * @param list<string> $templatePaths
     * @param list<string> $layoutPaths
     */
    public function __construct(
        private array $templatePaths,
        private array $layoutPaths,
        private ServerRequestInterface $request,
    ) {
    }

    public function supports(\ReflectionParameter $parameter): bool
    {
        return [] !== $parameter->getAttributes(TemplateAttribute::class);
    }

    /**
     * @param array<string, mixed> $providedParameters
     */
    public function resolve(\ReflectionParameter $parameter, array $providedParameters): Template
    {
        $attribute = $parameter->getAttributes(TemplateAttribute::class)[0]->newInstance();

        $template = new Template(
            name: $attribute->name,
            templatePaths: $this->templatePaths,
            layoutPaths: $this->layoutPaths,
            request: $this->request,
        );

        if (null !== $attribute->layout) {
            $template->layout($attribute->layout);
        }

        return $template;
    }
}
