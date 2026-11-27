<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/** A select box with a dropdown caret; InputCombo and InputComboTable resolve here. No options are loaded. */
class WireframeInputSelect extends WireframeInput
{
    /**
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeInput::buildHtmlBody()
     */
    protected function buildHtmlBody(): string
    {
        return $this->buildHtmlField('input select', 'Selection field', '<span class="glyph">&#9662;</span>');
    }
}
