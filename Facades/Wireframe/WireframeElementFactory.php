<?php
namespace axenox\GenAI\Facades\Wireframe;

use axenox\GenAI\Facades\Wireframe\Elements\WireframeBasicElement;
use axenox\GenAI\Facades\Wireframe\Elements\WireframeButton;
use exface\Core\CommonLogic\DataSheets\DataAggregation;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Widgets\DialogHeader;
use exface\Core\Widgets\InputHidden;

/**
 * Plays the facade role for wireframe elements: selects an element per projected node and builds
 * its HTML without creating live widgets. Metadata lookups are optional.
 */
class WireframeElementFactory
{
    /** @var array<string, class-string<WireframeBasicElement>> */
    private array $classes = [];

    /** @var array<string, array{children: array<string, string>, inner: string[]}> Discovered properties per Core widget class. */
    private array $discoveredProperties = [];

    /**
     * @param (\Closure(string): ?MetaObjectInterface)|null $resolveObject
     * @param (\Closure(string): ?array{prototype: string, config: array, object: ?string, name?: ?string})|null $resolveActionDefinition
     * @param (\Closure(class-string): array<string, string>)|null $resolveWidgetProperties
     *        Maps a widget class to its UXON properties and their `@uxon-type` annotations.
     * @param (\Closure(string): ?string)|null $resolveActionName Translated name of an action alias.
     * @param (\Closure(?string, int): ?array)|null $resolveMenu Permitted menu tree below a page (null = current page),
     *        as nodes `{label, description, url, children}`; `url` is set only for previewable pages.
     * @return void
     */
    public function __construct(
        private ?\Closure $resolveObject = null,
        private ?\Closure $resolveActionDefinition = null,
        private ?\Closure $resolveWidgetProperties = null,
        private ?\Closure $resolveActionName = null,
        private ?\Closure $resolveMenu = null
    ) {
    }

    /**
     * Menu tree for NavTiles; null if no menu lookup was supplied or the root is not available.
     *
     * @param string|null $rootPageAlias
     * @param int $depth
     * @return array<array{label: string, description: ?string, url: ?string, children: array}>|null
     */
    public function resolveMenu(?string $rootPageAlias, int $depth): ?array
    {
        return $this->resolveMenu === null ? null : ($this->resolveMenu)($rootPageAlias, $depth);
    }

    /**
     * Default button caption, as Core derives it from the action.
     *
     * @param string $alias
     * @return string|null
     */
    public function resolveActionName(string $alias): ?string
    {
        return $this->resolveActionName === null ? null : ($this->resolveActionName)($alias);
    }

    /**
     * Readable name of a meta object; null without an object lookup.
     *
     * @param string|null $objectAlias
     * @return string|null
     */
    public function resolveObjectName(?string $objectAlias): ?string
    {
        return $objectAlias === null ? null : $this->resolveObject($objectAlias)?->getName();
    }

    /**
     * Default caption of an attribute-bound widget, mirroring Core's AttributeCaptionTrait: the attribute
     * name, or the last relation's name for a related `__LABEL`. Aggregators such as `:LIST_DISTINCT`
     * are ignored. Only model metadata is read.
     *
     * @param string|null $objectAlias
     * @param string $attributeAlias
     * @return string|null
     */
    public function resolveAttributeCaption(?string $objectAlias, string $attributeAlias): ?string
    {
        $object = $objectAlias === null ? null : $this->resolveObject($objectAlias);
        if ($object === null) {
            return null;
        }
        $alias = DataAggregation::stripAggregator($attributeAlias);
        try {
            if (! $object->hasAttribute($alias)) {
                return null;
            }
            $attribute = $object->getAttribute($alias);
            $path = $attribute->getRelationPath();
            if (! $path->isEmpty() && strcasecmp(substr($alias, -7), '__LABEL') === 0) {
                return $path->getRelationLast()->getName();
            }
            return $attribute->getName();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Combine the element's declared child groups with the widget's discovered content properties.
     * Declared groups come first and win, so elements still control labels, order, and default types.
     *
     * @param WireframeBasicElement $element
     * @return array<string, string> UXON property names mapped to default child widget types.
     */
    public function childTypes(WireframeBasicElement $element): array
    {
        return $element->childTypes() + ($this->discoverProperties($element->getNode()['type'])['children'] ?? []);
    }

    /**
     * Name widget-typed properties that configure a part of the widget itself, such as a filter's
     * `input_widget`, a column's `cell_widget`, or a combo's lookup `table`. The parent already
     * represents them, so they are neither traversed nor reported as omitted.
     *
     * @param string $type
     * @return string[]
     */
    public function innerWidgetProperties(string $type): array
    {
        return $this->discoverProperties($type)['inner'] ?? [];
    }

    /**
     * @param string $type
     * @return array{children: array<string, string>, inner: string[]}|null
     */
    private function discoverProperties(string $type): ?array
    {
        if ($this->resolveWidgetProperties === null || null === $widgetClass = $this->findCoreWidgetClass($type)) {
            return null;
        }
        return $this->discoveredProperties[$widgetClass] ??= $this->classifyProperties($widgetClass);
    }

    /**
     * Treat widget lists and single containers as content; any other single widget is inner configuration.
     *
     * @param string $widgetClass
     * @return array{children: array<string, string>, inner: string[]}
     */
    private function classifyProperties(string $widgetClass): array
    {
        $found = ['children' => [], 'inner' => []];
        foreach (($this->resolveWidgetProperties)($widgetClass) as $property => $annotation) {
            if (! is_string($property) || ! preg_match('/^[a-z][a-z0-9_]*$/iD', $property)) {
                continue;
            }
            // Union types list alternatives; the first widget class decides the default child type.
            foreach (explode('|', (string) $annotation) as $candidate) {
                $candidate = trim($candidate);
                $isList = str_ends_with($candidate, '[]');
                $candidate = ltrim($isList ? substr($candidate, 0, -2) : $candidate, '\\');
                if ($candidate === '' || ! is_a($candidate, WidgetInterface::class, true)) {
                    continue;
                }
                if ($isList || is_a($candidate, iContainOtherWidgets::class, true)) {
                    $shortName = substr(strrchr('\\' . $candidate, '\\'), 1);
                    $found['children'][$property] = $shortName === 'AbstractWidget' ? 'Widget' : $shortName;
                } else {
                    $found['inner'][] = $property;
                }
                break;
            }
        }
        return $found;
    }

    /**
     * Resolve object metadata only when a lookup was supplied for projection.
     *
     * @param string $alias
     * @return MetaObjectInterface|null
     */
    public function resolveObject(string $alias): ?MetaObjectInterface
    {
        return $this->resolveObject === null ? null : ($this->resolveObject)($alias);
    }

    /**
     * Resolve a saved action definition, not an executable action.
     *
     * @param string $alias
     * @return array{prototype: string, config: array, object: ?string}|null
     */
    public function resolveActionDefinition(string $alias): ?array
    {
        return $this->resolveActionDefinition === null ? null : ($this->resolveActionDefinition)($alias);
    }

    /**
     * Build the HTML for a projected node, like `AbstractAjaxFacade::buildHtml()` does for a widget.
     *
     * @param array $node
     * @return string
     */
    public function buildHtml(array $node): string
    {
        return $this->getElement($node)->buildHtml();
    }

    /**
     * Get the element for a projected node, like `AbstractAjaxFacade::getElement()` does for a widget.
     * Projection and rendering use the same selection; action-bearing nodes always use the button
     * element to keep actions inert.
     *
     * @param array $node
     * @return WireframeBasicElement
     */
    public function getElement(array $node): WireframeBasicElement
    {
        $class = isset($node['action']) ? WireframeButton::class
            : ($this->classes[strtolower($node['type'])] ??= $this->getElementClassForType($node['type']));
        return new $class($node, $this);
    }

    /**
     * Find `Wireframe{WidgetType}` in the element namespace for the widget type or its closest Core
     * widget ancestor, falling back to `WireframeBasicElement`, the same convention as the AJAX facades.
     *
     * @param string $type
     * @return class-string<WireframeBasicElement>
     */
    protected function getElementClassForType(string $type): string
    {
        $widgetClass = $this->findCoreWidgetClass($type);
        // Resolve the family without constructing the live widget.
        for (; $widgetClass !== null && $widgetClass !== false; $widgetClass = get_parent_class($widgetClass)) {
            $elementClass = $this->getElementNamespace() . 'Wireframe' . substr(strrchr('\\' . $widgetClass, '\\'), 1);
            if (class_exists($elementClass) && is_subclass_of($elementClass, WireframeBasicElement::class)
                && ! (new \ReflectionClass($elementClass))->isAbstract()) {
                return $elementClass;
            }
        }
        return WireframeBasicElement::class;
    }

    /**
     * Override in a subclass to look up elements in another namespace, like the AJAX facades' class namespace.
     *
     * @return string
     */
    protected function getElementNamespace(): string
    {
        return 'axenox\\GenAI\\Facades\\Wireframe\\Elements\\';
    }

    /**
     * Widget type Core creates for an attribute-bound widget without `widget_type`: the attribute's
     * default editor, or its default display in read-only containers (see WidgetFactory::getDefaults()).
     *
     * @param string|null $objectAlias
     * @param string $attributeAlias
     * @param bool $readOnly
     * @return string|null
     */
    public function resolveAttributeWidgetType(?string $objectAlias, string $attributeAlias, bool $readOnly): ?string
    {
        $object = $objectAlias === null ? null : $this->resolveObject($objectAlias);
        if ($object === null) {
            return null;
        }
        $alias = DataAggregation::stripAggregator($attributeAlias);
        try {
            if (! $object->hasAttribute($alias)) {
                return null;
            }
            $attribute = $object->getAttribute($alias);
            $uxon = $readOnly ? $attribute->getDefaultDisplayUxon() : $attribute->getDefaultEditorUxon();
            $type = $uxon->getProperty('widget_type');
            return is_string($type) && $type !== '' ? $type : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Containers that only display values, so their attribute-bound children default to display widgets.
     *
     * @param string $type
     * @return bool
     */
    public function isReadOnlyType(string $type): bool
    {
        $widgetClass = $this->findCoreWidgetClass($type);
        return $widgetClass !== null && is_a($widgetClass, DialogHeader::class, true);
    }

    /**
     * Widgets the normal UI never displays, such as `InputHidden`, are left out of the preview.
     *
     * @param string $type
     * @return bool
     */
    public function isInvisibleType(string $type): bool
    {
        $widgetClass = $this->findCoreWidgetClass($type);
        return $widgetClass !== null && is_a($widgetClass, InputHidden::class, true);
    }

    /**
     * Map a configured type to its Core widget class, accepting only plain names to keep autoloading in Core.
     *
     * @param string $type
     * @return string|null
     */
    private function findCoreWidgetClass(string $type): ?string
    {
        $type = preg_replace('/^exface\.Core\./i', '', $type);
        if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $type)) {
            return null;
        }
        $widgetClass = ltrim(WidgetFactory::getWidgetClassFromType($type), '\\');
        return class_exists($widgetClass) ? $widgetClass : null;
    }
}
