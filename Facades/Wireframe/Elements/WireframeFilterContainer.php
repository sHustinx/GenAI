<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/**
 * Allows widgets with a filter group to preview it without loading filter values.
 * Abstract because no Core widget has this name; concrete elements extend it.
 */
abstract class WireframeFilterContainer extends WireframeBasicElement
{
    protected const CHILD_TYPES = parent::CHILD_TYPES + ['filters' => 'Filter'];
}
