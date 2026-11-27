<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/** Shows a map placeholder and its configured layers without loading tiles, positions, or layer data. */
class WireframeMap extends WireframeButtonContainer
{
    protected const SHOW_OBJECT = true;

    /**
     * Describe each layer by caption, type, and data object; never read coordinates or row data.
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeBasicElement::projectDetails()
     */
    public function projectDetails(array $config, callable $resolvePageUrl, callable $registerDialog): array
    {
        $layers = [];
        foreach ((array) ($config['layers'] ?? []) as $layer) {
            if (! is_array($layer) || in_array($layer['hidden'] ?? false, [true, 1, '1', 'true'], true)) {
                continue;
            }
            $caption = self::configuredText($layer['caption'] ?? null);
            $type = self::configuredText($layer['type'] ?? null);
            $object = is_array($layer['data'] ?? null) ? self::configuredText($layer['data']['object_alias'] ?? null) : null;
            $link = self::configuredText($layer['data_widget_link'] ?? null);
            $label = implode(' / ', array_filter([$caption, $type], static fn (?string $text): bool => $text !== null));
            if ($label === '') {
                continue;
            }
            if ($object !== null) {
                $label .= ' over ' . $object;
            } elseif ($link !== null) {
                $label .= ' linked to ' . $link;
            }
            $layers[] = $label;
        }
        return $layers === [] ? [] : ['layers' => $layers];
    }

    /**
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeBasicElement::buildHtmlBody()
     */
    protected function buildHtmlBody(): string
    {
        return '<span class="map-area" role="img" aria-label="Map" title="Map (no tiles or positions in preview)">Map</span>'
            . $this->buildHtmlTextList('Layers', $this->getNode()['layers'] ?? []);
    }
}
