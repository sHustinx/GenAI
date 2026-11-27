<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/** Represents a display widget without evaluating its value or rendering embedded markup. */
class WireframeDisplay extends WireframeBasicElement
{
    /**
     * A muted bar where the value would appear; subclasses shape it like their control.
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeBasicElement::buildHtmlBody()
     */
    protected function buildHtmlBody(): string
    {
        return $this->buildHtmlField('display', 'Displayed value');
    }
}
