<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/** Shows a dialog's target object and configured controls without prefilling any fields. */
class WireframeDialog extends WireframeForm
{
    protected const SHOW_OBJECT = true;
    // Declared first so the header renders above the dialog's widgets, as in the normal UI.
    protected const CHILD_TYPES = ['header' => 'DialogHeader'] + parent::CHILD_TYPES;
}
