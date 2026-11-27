<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/**
 * Shared child groups for widgets with toolbar controls or directly configured buttons.
 * Abstract because no Core widget has this name; concrete elements extend it.
 */
abstract class WireframeButtonContainer extends WireframeBasicElement
{
    protected const CHILD_TYPES = parent::CHILD_TYPES + [
        'toolbars' => 'Toolbar',
        'toolbar' => 'Toolbar',
        'buttons' => 'Button',
    ];
}
