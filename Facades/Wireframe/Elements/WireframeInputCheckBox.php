<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/** A single checkbox square. */
class WireframeInputCheckBox extends WireframeInput
{
    /**
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeInput::buildHtmlBody()
     */
    protected function buildHtmlBody(): string
    {
        return $this->buildHtmlField('checkbox', 'Checkbox');
    }
}
