<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/** Lays out header widget groups side by side, wrapping to the next line like the real dialog header. */
class WireframeDialogHeader extends WireframeBasicElement
{
    /**
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeBasicElement::buildCssChildGroupClass()
     */
    protected function buildCssChildGroupClass(string $group): string
    {
        return $group === 'widgets' ? 'group flow' : parent::buildCssChildGroupClass($group);
    }
}
