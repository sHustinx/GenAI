<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

use axenox\GenAI\Facades\Wireframe\WireframeElementFactory;

/**
 * Base element bound to one projected node, mirroring the AJAX facade elements: `buildHtml()`
 * assembles small `buildHtml...()` and `buildCss...()` hooks that subclasses override one at a time.
 * Unknown widget types use this labeled box, which still renders their children.
 *
 * Elements are found by convention: `Wireframe{WidgetType}` in this namespace, else the element of the
 * closest Core widget ancestor (see `WireframeElementFactory::getElementClassForType()`).
 */
class WireframeBasicElement
{
    protected const SHOW_OBJECT = false;
    protected const CHILD_TYPES = ['widgets' => 'Widget'];

    /** Groups whose children are laid out directly, without a group label. */
    protected const UNLABELED_GROUPS = ['widgets', 'panels', 'tabs'];

    /**
     * @param array $node
     * @param WireframeElementFactory $factory
     * @return void
     */
    public function __construct(private array $node, private WireframeElementFactory $factory)
    {
    }

    /**
     * The projected node; never raw UXON or live widget values.
     *
     * @return array
     */
    public function getNode(): array
    {
        return $this->node;
    }

    /**
     * The factory selecting elements for child nodes, like `getFacade()` in the AJAX elements.
     *
     * @return WireframeElementFactory
     */
    public function getFactory(): WireframeElementFactory
    {
        return $this->factory;
    }

    /**
     * Declare child groups that need a specific order, default type, or rendering.
     * Other widget-typed properties are discovered from the UXON schema by the factory.
     *
     * @return array<string, string> UXON property names mapped to default child widget types.
     */
    public function childTypes(): array
    {
        return static::CHILD_TYPES;
    }

    /**
     * Return only widget-specific preview fields, not raw UXON or runtime values. Called while the
     * node is being projected, so `getNode()` holds only the common fields at this point.
     * A widget that opens a dialog passes its dialog UXON to `$registerDialog`; the dialog is projected
     * only when its own preview is requested, and the returned stub (caption, object) goes into `dialog`.
     *
     * @param array $config
     * @param callable(string): ?string $resolvePageUrl
     * @param callable(array): array $registerDialog
     * @return array
     */
    public function projectDetails(array $config, callable $resolvePageUrl, callable $registerDialog): array
    {
        return [];
    }

    /**
     * @return string
     */
    public function buildHtml(): string
    {
        return '<section class="' . $this->buildCssElementClass() . '">'
            . $this->buildHtmlHeader()
            . $this->buildHtmlBody()
            . $this->buildHtmlOmitted()
            . $this->buildHtmlChildren()
            . '</section>';
    }

    /**
     * Caption, widget type, and (for data-bound elements) the meta object.
     *
     * @return string
     */
    protected function buildHtmlHeader(): string
    {
        $node = $this->getNode();
        $type = $node['type'];
        $caption = $node['caption'] ?? $type;
        $html = '<header><strong>' . self::escape($caption) . '</strong>';
        if ($caption !== $type) {
            $html .= ' <small>' . self::escape($type) . '</small>';
        }
        if (static::SHOW_OBJECT && isset($node['object'])) {
            $html .= ' <small>over ' . (isset($node['objectName']) ? self::escape($node['objectName']) . ' ' : '')
                . '<code>' . self::escape($node['object']) . '</code></small>';
        }
        return $html . '</header>';
    }

    /**
     * Widget-specific content between the header and the child groups.
     *
     * @return string
     */
    protected function buildHtmlBody(): string
    {
        return '';
    }

    /**
     * Name configured widget properties the preview does not traverse; defaults to this node.
     *
     * @param array|null $node
     * @return string
     */
    protected function buildHtmlOmitted(?array $node = null): string
    {
        $omitted = ($node ?? $this->getNode())['omitted'] ?? [];
        if ($omitted === []) {
            return '';
        }
        return ' <small class="omitted">Not shown in preview: ' . self::escape(implode(', ', $omitted)) . '</small>';
    }

    /**
     * @return string
     */
    protected function buildHtmlChildren(): string
    {
        $html = '';
        foreach ($this->getNode()['children'] as $group => $children) {
            if (! in_array($group, static::UNLABELED_GROUPS, true)) {
                $html .= '<div class="group-label">' . self::escape(ucfirst(str_replace('_', ' ', $group))) . '</div>';
            }
            $html .= $this->buildHtmlChildGroup($group, $children);
        }
        return $html;
    }

    /**
     * Render one child group; each child goes through the factory so it uses its own element.
     *
     * @param string $group
     * @param array $children
     * @return string
     */
    protected function buildHtmlChildGroup(string $group, array $children): string
    {
        $html = '<div class="' . $this->buildCssChildGroupClass($group) . '">';
        foreach ($children as $child) {
            $html .= $this->getFactory()->buildHtml($child);
        }
        return $html . '</div>';
    }

    /**
     * A non-interactive value placeholder shaped like the real control; `$inner` adds shape details
     * (a caret, a fill, ...). No values are ever shown.
     *
     * @param string $cssClass
     * @param string $label
     * @param string $inner
     * @return string
     */
    protected function buildHtmlField(string $cssClass, string $label, string $inner = ''): string
    {
        return '<span class="field ' . $cssClass . '" role="img" aria-label="' . self::escape($label) . '" title="'
            . self::escape($label . ' (no data in preview)') . '">' . $inner . '</span>';
    }

    /**
     * A labeled list of plain preview texts, such as chart series or map layers.
     *
     * @param string $label
     * @param array $items
     * @return string
     */
    protected function buildHtmlTextList(string $label, array $items): string
    {
        if ($items === []) {
            return '';
        }
        $html = '<div class="group-label">' . self::escape($label) . '</div><ul>';
        foreach ($items as $item) {
            $html .= '<li>' . self::escape($item) . '</li>';
        }
        return $html . '</ul>';
    }

    /**
     * @return string
     */
    protected function buildCssElementClass(): string
    {
        return 'widget';
    }

    /** Groups listed here lay their children out as a wrapping toolbar row. */
    protected const BUTTON_GROUPS = ['buttons', 'button_groups'];

    /**
     * @param string $group
     * @return string
     */
    protected function buildCssChildGroupClass(string $group): string
    {
        return in_array($group, static::BUTTON_GROUPS, true) ? 'group buttons' : 'group';
    }

    /**
     * @param string $value
     * @return string
     */
    protected static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Ignore blank text and non-string configuration values.
     *
     * @param mixed $value
     * @return string|null
     */
    protected static function configuredText(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
