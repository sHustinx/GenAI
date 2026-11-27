<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/**
 * Renders tabs as a real tab strip. The preview allows no scripts, so switching uses radio buttons
 * and CSS only (see `.tabset` in page.php); the first tab is open initially.
 */
class WireframeTabs extends WireframeBasicElement
{
    protected const CHILD_TYPES = parent::CHILD_TYPES + ['tabs' => 'Tab'];

    /** Core accepts tabs under both properties. */
    protected const TAB_GROUPS = ['tabs', 'widgets'];

    /** Keeps radio groups apart when a page contains several tab widgets. */
    private static int $tabsetCount = 0;

    /**
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeBasicElement::buildHtmlChildGroup()
     */
    protected function buildHtmlChildGroup(string $group, array $children): string
    {
        if (! in_array($group, static::TAB_GROUPS, true)) {
            return parent::buildHtmlChildGroup($group, $children);
        }
        $name = 'tabset-' . ++self::$tabsetCount;
        $html = '<div class="tabset">';
        foreach (array_values($children) as $i => $tab) {
            $id = $name . '-' . $i;
            $html .= '<input type="radio" name="' . $name . '" id="' . $id . '"' . ($i === 0 ? ' checked' : '') . '>'
                . '<label for="' . $id . '">' . self::escape($this->buildTextTabCaption($tab, $i)) . '</label>'
                . '<div class="tab-panel">' . $this->getFactory()->buildHtml($tab) . '</div>';
        }
        return $html . '</div>';
    }

    /**
     * @param array $tab
     * @param int $index
     * @return string
     */
    protected function buildTextTabCaption(array $tab, int $index): string
    {
        return $tab['caption'] ?? 'Tab ' . ($index + 1);
    }
}
