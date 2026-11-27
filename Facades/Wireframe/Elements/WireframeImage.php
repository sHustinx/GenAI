<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/** An image frame; no image is loaded. */
class WireframeImage extends WireframeDisplay
{
    /**
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeDisplay::buildHtmlBody()
     */
    protected function buildHtmlBody(): string
    {
        return $this->buildHtmlField('image', 'Image');
    }
}
