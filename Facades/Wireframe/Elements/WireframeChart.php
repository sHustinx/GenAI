<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/** Reuses the inactive data control but describes chart series rather than table columns. */
class WireframeChart extends WireframeData
{
    protected const CHILD_TYPES = WireframeButtonContainer::CHILD_TYPES;

    /**
     * Extract configured series and axis labels without reading chart data.
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeBasicElement::projectDetails()
     */
    public function projectDetails(array $config, callable $resolvePageUrl, callable $registerDialog): array
    {
        $seriesLabels = [];
        foreach ((array) ($config['series'] ?? []) as $series) {
            if (! is_array($series)) {
                continue;
            }
            $labels = array_filter([
                self::configuredText($series['caption'] ?? $series['type'] ?? null),
                self::configuredText($series['x_attribute_alias'] ?? null),
                self::configuredText($series['y_attribute_alias'] ?? $series['value_attribute_alias'] ?? null),
            ], static fn (?string $label): bool => $label !== null);
            if ($labels !== []) {
                $seriesLabels[] = implode(' / ', $labels);
            }
        }
        return $seriesLabels === [] ? [] : ['series' => $seriesLabels];
    }

    /**
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeData::buildHtmlBody()
     */
    protected function buildHtmlBody(): string
    {
        return parent::buildHtmlBody() . $this->buildHtmlTextList('Series', $this->getNode()['series'] ?? []);
    }
}
