<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/** Preserves toolbar nesting for both grouped buttons and direct buttons. */
class WireframeToolbar extends WireframeBasicElement
{
    protected const CHILD_TYPES = parent::CHILD_TYPES + [
        'button_groups' => 'ButtonGroup',
        'buttons' => 'Button',
    ];
}
