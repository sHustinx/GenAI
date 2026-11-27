<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/** Adds configured schedule item structure to the common data-widget preview. */
class WireframeScheduler extends WireframeData
{
    protected const CHILD_TYPES = parent::CHILD_TYPES + ['items' => 'Item'];
}
