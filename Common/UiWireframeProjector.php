<?php
namespace axenox\GenAI\Common;

use axenox\GenAI\Facades\Wireframe\WireframeElementFactory;

/**
 * Projects saved UXON onto a structural tree without instantiating widgets or actions.
 * Elements declare which widget-specific details to keep; the projector owns traversal.
 *
 * Each call projects exactly one view: the page, or one dialog. Dialogs are not expanded in the view
 * that links to them; a button only gets a dialog stub (caption and object) plus its `dialogStep`, the
 * button's position in that view. `projectDialog()` follows such steps one view at a time. Dialog cycles
 * (an editor linking back to itself) therefore never recurse on the server; they just make the link
 * longer, and the caller limits the number of steps.
 */
final class UiWireframeProjector
{
    /** Dialog configurations registered while projecting the current view, keyed by button position. */
    private array $dialogs = [];

    /**
     * @param WireframeElementFactory $elements
     * @return void
     */
    public function __construct(private WireframeElementFactory $elements = new WireframeElementFactory())
    {
    }

    /**
     * Project one saved widget tree. A hidden root yields no preview.
     *
     * @param array $config
     * @param callable(string): ?string $resolvePageUrl Returns a URL only for a permitted target.
     * @return array|null
     */
    public function project(array $config, callable $resolvePageUrl): ?array
    {
        $this->dialogs = [];
        return $this->projectNode($config, 'Widget', null, $resolvePageUrl, 'self');
    }

    /**
     * Project the dialog reached by following `dialogStep`s from the page: each step names a button in
     * the previous view. Only the views along the path are projected.
     *
     * @param array $config
     * @param callable(string): ?string $resolvePageUrl
     * @param string[] $steps
     * @return array{node: array, title: string}|null Null if a step does not lead to a dialog.
     */
    public function projectDialog(array $config, callable $resolvePageUrl, array $steps): ?array
    {
        $node = $this->project($config, $resolvePageUrl);
        $title = null;
        foreach ($steps as $step) {
            $dialog = $node === null ? null : ($this->dialogs[$step] ?? null);
            if ($dialog === null) {
                return null;
            }
            $title = $dialog['title'];
            $this->dialogs = [];
            $node = $this->projectNode($dialog['config'], 'Dialog', $dialog['object'], $resolvePageUrl, 'self');
        }
        return $node === null || $title === null ? null : ['node' => $node, 'title' => $title];
    }

    /**
     * Apply shared visibility and traversal rules to a widget and its children within the current view.
     *
     * @param array $config
     * @param string $defaultType
     * @param string|null $objectAlias
     * @param callable(string): ?string $resolvePageUrl
     * @param string $path Position in the current view (`self`, then `group/index` pairs), used as dialog step.
     * @param bool $readOnly Whether the parent container only displays values, like Core's Container::isReadonly().
     * @return array|null
     */
    private function projectNode(
        array $config,
        string $defaultType,
        ?string $objectAlias,
        callable $resolvePageUrl,
        string $path,
        bool $readOnly = false
    ): ?array {
        if (in_array($config['hidden'] ?? false, [true, 1, '1', 'true'], true)
            || in_array($config['visibility'] ?? null, [10, '10'], true)
            || strcasecmp($this->text($config['visibility'] ?? null) ?? '', 'hidden') === 0) {
            return null;
        }
        $objectAlias = $this->text($config['object_alias'] ?? null) ?? $objectAlias;
        $type = $this->text($config['widget_type'] ?? null) ?? $this->defaultType($config, $defaultType, $objectAlias, $readOnly);
        if ($this->elements->isInvisibleType($type)) {
            return null;
        }

        $action = $config['action'] ?? [];
        $actionConfig = is_array($action) ? $action : [];
        $actionAlias = $this->text($actionConfig['alias'] ?? $config['action_alias'] ?? $action);
        $node = [
            'type' => $type,
            'caption' => $this->text($config['caption'] ?? null) ?? $this->defaultCaption($config, $objectAlias),
            'object' => $objectAlias,
            'action' => $actionAlias,
            'children' => [],
        ];
        $objectName = $this->elements->resolveObjectName($objectAlias);
        if ($objectName !== null) {
            $node['objectName'] = $objectName;
        }
        $element = $this->elements->getElement($node);
        $node += $element->projectDetails(
            $config,
            $resolvePageUrl,
            // Register the dialog for a later request instead of projecting it into this view.
            function (array $dialog) use ($objectAlias, $path): array {
                $dialogObject = $this->text($dialog['object_alias'] ?? null) ?? $objectAlias;
                $caption = $this->text($dialog['caption'] ?? null);
                $this->dialogs[$path] = ['config' => $dialog, 'object' => $dialogObject, 'title' => $caption];
                return ['type' => 'Dialog', 'caption' => $caption, 'object' => $dialogObject, 'children' => []];
            }
        );
        if (is_array($node['dialog'] ?? null)) {
            $node['dialogStep'] = $path;
            // Without its own caption, a dialog is titled like the button that opens it.
            $this->dialogs[$path]['title'] ??= $node['caption'] ?? $node['actionName'] ?? 'Dialog preview';
        }

        // Child groups can be configured as a single UXON object or a list.
        $childTypes = $this->elements->childTypes($element);
        $childrenReadOnly = $readOnly || in_array($config['readonly'] ?? false, [true, 1, '1', 'true'], true)
            || $this->elements->isReadOnlyType($type);
        foreach ($childTypes as $key => $childType) {
            $children = $config[$key] ?? null;
            if (! is_array($children)) {
                continue;
            }
            if (! array_is_list($children)) {
                $children = [$children];
            }
            foreach ($children as $child) {
                if (! is_array($child) || $child === []) {
                    continue;
                }
                $childPath = ($path === 'self' ? '' : $path . '/') . $key . '/' . count($node['children'][$key] ?? []);
                $projectedChild = $this->projectNode($child, $childType, $objectAlias, $resolvePageUrl, $childPath, $childrenReadOnly);
                if ($projectedChild !== null) {
                    $node['children'][$key][] = $projectedChild;
                }
            }
        }
        $omitted = $this->findOmittedWidgetProperties(
            $config,
            $childTypes + array_fill_keys($this->elements->innerWidgetProperties($type), true)
        );
        if ($omitted !== []) {
            $node['omitted'] = $omitted;
        }
        return $node;
    }

    /**
     * Name configured properties that hold widget UXON but are not traversed, so nothing disappears silently.
     * Only property names are kept; their contents stay out of the preview.
     *
     * @param array $config
     * @param array $childTypes
     * @return string[]
     */
    private function findOmittedWidgetProperties(array $config, array $childTypes): array
    {
        $omitted = [];
        foreach ($config as $key => $value) {
            if (! is_string($key) || $key === 'action' || isset($childTypes[$key]) || ! is_array($value)
                || ! preg_match('/^[a-z][a-z0-9_]*$/iD', $key)) {
                continue;
            }
            $first = array_is_list($value) ? ($value[0] ?? null) : $value;
            if (is_array($first) && is_string($first['widget_type'] ?? null)) {
                $omitted[] = $key;
            }
        }
        return $omitted;
    }

    /**
     * Pick the type of a widget without `widget_type` like WidgetFactory::getDefaults(): a typed child
     * group (columns, filters, tabs, ...) decides; in generic `widget` groups an attribute-bound widget
     * becomes the attribute's default editor, or its default display inside read-only containers.
     *
     * @param array $config
     * @param string $groupType
     * @param string|null $objectAlias
     * @param bool $readOnly
     * @return string
     */
    private function defaultType(array $config, string $groupType, ?string $objectAlias, bool $readOnly): string
    {
        $attributeAlias = $this->text($config['attribute_alias'] ?? null);
        if ($groupType !== 'Widget' || $attributeAlias === null) {
            return $groupType;
        }
        return $this->elements->resolveAttributeWidgetType($objectAlias, $attributeAlias, $readOnly) ?? $groupType;
    }

    /**
     * Name attribute-bound widgets like Core does; fall back to the alias when no metadata is available.
     * Columns bound to an attribute group show the group alias instead of a bare widget type.
     *
     * @param array $config
     * @param string|null $objectAlias
     * @return string|null
     */
    private function defaultCaption(array $config, ?string $objectAlias): ?string
    {
        $attributeAlias = $this->text($config['attribute_alias'] ?? null);
        if ($attributeAlias !== null) {
            return $this->elements->resolveAttributeCaption($objectAlias, $attributeAlias) ?? $attributeAlias;
        }
        return $this->text($config['attribute_group_alias'] ?? null);
    }

    /**
     * Accept only configured text; do not coerce structured UXON values into labels.
     *
     * @param mixed $value
     * @return string|null
     */
    private function text($value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
