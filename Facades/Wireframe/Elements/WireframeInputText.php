<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/** A taller multi-line text box; InputMarkdown and other text editors resolve here. */
class WireframeInputText extends WireframeInput
{
    /**
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeInput::buildHtmlBody()
     */
    protected function buildHtmlBody(): string
    {
        return $this->buildHtmlField('input textarea', 'Text area');
    }
}
