<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/** A neutral color dot next to the value bar; the real color depends on data and is never shown. */
class WireframeColorIndicator extends WireframeDisplay
{
    /**
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeDisplay::buildHtmlBody()
     */
    protected function buildHtmlBody(): string
    {
        return $this->buildHtmlField('display color-indicator', 'Color indicator', '<span class="dot"></span>');
    }
}
