<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/** A date box with a calendar mark; InputDateTime resolves here. */
class WireframeInputDate extends WireframeInput
{
    /**
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeInput::buildHtmlBody()
     */
    protected function buildHtmlBody(): string
    {
        return $this->buildHtmlField('input date', 'Date field', '<span class="glyph">&#128197;&#xFE0E;</span>');
    }
}
