<?php

namespace App\Modules\Templates\Http\Resources;

/**
 * docs/45 §45.4.4 — `TemplateV2` list item: `html_content` is null.
 */
class TemplateListResource extends TemplateResource
{
    protected bool $omitHtml = true;
}
