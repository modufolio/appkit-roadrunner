<?php

declare(strict_types=1);

namespace App\Attributes;

/**
 * Marks a controller parameter to be resolved into a ready-to-render
 * {@see \Modufolio\Appkit\Template\Template} instance.
 *
 * The template and layout paths and the current request are wired up
 * automatically by {@see \App\Resolver\TemplateResolver}, so controllers
 * only declare the template name:
 *
 *   public function index(#[Template('home')] TemplateEngine $template): ResponseInterface
 *   {
 *       return new Response(body: $template->render(['title' => 'Welcome']));
 *   }
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Template
{
    public function __construct(
        public string $name,
        public ?string $layout = null,
    ) {
    }
}
