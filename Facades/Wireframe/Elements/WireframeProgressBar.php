<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/** A progress track with a fixed partial fill; the real progress depends on data and is never shown. */
class WireframeProgressBar extends WireframeDisplay
{
    /**
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeDisplay::buildHtmlBody()
     */
    protected function buildHtmlBody(): string
    {
        return $this->buildHtmlField('progress', 'Progress bar', '<span class="fill"></span>');
    }
}
