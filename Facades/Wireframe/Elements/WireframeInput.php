<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/** Shows that a field accepts input in the normal UI, without creating an editable control. */
class WireframeInput extends WireframeFilterContainer
{
    /**
     * An empty input box; subclasses shape it like their control. No values are ever filled in.
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeBasicElement::buildHtmlBody()
     */
    protected function buildHtmlBody(): string
    {
        return $this->buildHtmlField('input', 'Input field');
    }
}
