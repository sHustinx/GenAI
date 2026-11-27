<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/** Lets the table preview traverse columns nested inside a configured column group. */
class WireframeDataColumnGroup extends WireframeBasicElement
{
    protected const CHILD_TYPES = parent::CHILD_TYPES + ['columns' => 'DataColumn'];
}
