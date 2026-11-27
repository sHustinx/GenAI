<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/** Covers Data-, Chart-, Map-, and DashboardConfigurator: their tabs plus the configured filters. */
class WireframeWidgetConfigurator extends WireframeTabs
{
    protected const CHILD_TYPES = parent::CHILD_TYPES + ['filters' => 'Filter'];
}
