<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/** Turns supported actions into preview links and leaves all other actions disabled. */
class WireframeButton extends WireframeBasicElement
{
    protected const CHILD_TYPES = parent::CHILD_TYPES + ['buttons' => 'Button'];

    /** Core object dialogs previewed from the object's editor; values are the dialog caption prefix. */
    private const OBJECT_DIALOGS = [
        'exface.core.showobjecteditdialog' => 'Edit',
        'exface.core.showobjectcreatedialog' => 'Create',
        'exface.core.showobjectcopydialog' => 'Copy',
        'exface.core.showobjectinfodialog' => 'Info',
    ];

    /** Dialogs that only display the object, so fields are shown as displays rather than inputs. */
    private const READ_ONLY_DIALOGS = ['Info'];

    /**
     * Describe page links and supported dialogs without creating an action, and name the action like Core does.
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeBasicElement::projectDetails()
     */
    public function projectDetails(array $config, callable $resolvePageUrl, callable $registerDialog): array
    {
        $alias = $this->getNode()['action'] ?? '';
        $actionConfig = is_array($config['action'] ?? null) ? $config['action'] : [];
        $name = self::configuredText($actionConfig['name'] ?? null)
            ?? ($alias === '' ? null : $this->getFactory()->resolveActionName($alias));
        return $this->projectAction($config, $actionConfig, $resolvePageUrl, $registerDialog)
            + ($name === null ? [] : ['actionName' => $name]);
    }

    /**
     * @param array $config
     * @param array $actionConfig
     * @param callable(string): ?string $resolvePageUrl
     * @param callable(array): array $registerDialog
     * @return array
     */
    private function projectAction(array $config, array $actionConfig, callable $resolvePageUrl, callable $registerDialog): array
    {
        $node = $this->getNode();
        $alias = $node['action'] ?? '';
        $action = $this->normalizePrototype($alias);
        if ($action === 'exface.core.gotopage') {
            $target = self::configuredText($actionConfig['page_alias'] ?? $config['page_alias'] ?? null);
            return $target === null ? [] : ['pageUrl' => $resolvePageUrl($target)];
        }
        $modeledObject = null;
        if ($alias !== '' && $action !== 'exface.core.showdialog' && ! isset(self::OBJECT_DIALOGS[$action])) {
            $definition = $this->getFactory()->resolveActionDefinition($alias);
            if ($definition !== null) {
                $action = $this->normalizePrototype($definition['prototype']);
                $savedConfig = $definition['config'];
                // Both keys name the same setting; an inline override replaces either stored spelling.
                if (array_key_exists('dialog', $actionConfig) || array_key_exists('widget', $actionConfig)) {
                    unset($savedConfig['dialog'], $savedConfig['widget']);
                }
                $actionConfig += $savedConfig;
                $modeledObject = $definition['object'];
            }
        }
        $mode = self::OBJECT_DIALOGS[$action] ?? null;
        if ($action !== 'exface.core.showdialog' && $mode === null) {
            return str_contains($action, 'dialog') ? ['dialog' => null] : [];
        }
        $dialog = $actionConfig['dialog'] ?? $actionConfig['widget'] ?? null;
        $objectAlias = self::configuredText($actionConfig['object_alias'] ?? null) ?? $modeledObject ?? $node['object'];
        if ($dialog === null && $mode !== null && $objectAlias !== null
            && ! isset($actionConfig['widget_id']) && ! isset($actionConfig['page_alias'])) {
            $dialog = $this->projectObjectEditor($objectAlias, $mode);
        }
        if (! is_array($dialog) || array_is_list($dialog)) {
            return ['dialog' => null];
        }
        if ($objectAlias !== null) {
            $dialog += ['object_alias' => $objectAlias];
        }
        if (isset($actionConfig['dialog_buttons']) && is_array($actionConfig['dialog_buttons'])) {
            // Either side may be a single UXON object instead of a list.
            $list = static fn ($buttons): array => is_array($buttons) && ! array_is_list($buttons) ? [$buttons] : (array) $buttons;
            $dialog['buttons'] = array_merge($list($dialog['buttons'] ?? []), $list($actionConfig['dialog_buttons']));
        }
        return ['dialog' => $registerDialog($dialog)];
    }

    /**
     * Core stores prototypes as aliases, class names, or package-relative PHP paths.
     *
     * @param string $prototype
     * @return string
     */
    private function normalizePrototype(string $prototype): string
    {
        $prototype = strtolower(str_replace('\\', '/', ltrim($prototype, '\\')));
        return preg_replace('~^exface/core/actions/([a-z0-9_]+)(?:\.php)?$~D', 'exface.core.$1', $prototype);
    }

    /**
     * Use saved editor structure; otherwise describe editable attributes without building runtime editors.
     *
     * @param string $objectAlias
     * @param string $mode
     * @return array|null
     */
    private function projectObjectEditor(string $objectAlias, string $mode): ?array
    {
        $object = $this->getFactory()->resolveObject($objectAlias);
        if ($object === null) {
            return null;
        }
        $dialog = [
            'widget_type' => 'Dialog',
            'caption' => $mode . ' ' . $object->getName(),
            'object_alias' => $object->getAliasWithNamespace(),
        ];
        // Info dialogs reuse the editor but only display values, so attribute-bound fields become displays.
        if (in_array($mode, self::READ_ONLY_DIALOGS, true)) {
            $dialog['readonly'] = true;
        }
        $editor = $object->getDefaultEditorUxon()->toArray();
        if ($editor !== []) {
            $type = strtolower(self::configuredText($editor['widget_type'] ?? null) ?? 'dialog');
            if (in_array($type, ['dialog', 'exface.core.dialog'], true)) {
                return $editor + $dialog;
            }
            return $dialog + ['widgets' => [$editor]];
        }
        $fields = [];
        $readOnly = in_array($mode, self::READ_ONLY_DIALOGS, true);
        foreach ($object->getAttributes() as $attribute) {
            if ($attribute->isHidden() || (! $readOnly && (! $attribute->isEditable() || $attribute->getFixedValue()))) {
                continue;
            }
            $fields[] = ['widget_type' => $readOnly ? 'Display' : 'Input', 'caption' => $attribute->getName()];
        }
        return $dialog + ['widgets' => $fields];
    }

    /**
     * A button is a single chip rather than a labeled box; button groups lay chips out in a row.
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeBasicElement::buildHtml()
     */
    public function buildHtml(): string
    {
        return $this->buildHtmlButton() . $this->buildHtmlOmitted() . $this->buildHtmlChildren();
    }

    /**
     * All buttons share one chip shape: links for previewable targets, an inert chip otherwise.
     * The full caption is always shown; what a click does (or why it is inert) is given as a tooltip.
     *
     * @return string
     */
    protected function buildHtmlButton(): string
    {
        $node = $this->getNode();
        $caption = self::escape($this->buildTextCaption());
        if (isset($node['pageUrl'])) {
            return '<a class="button" href="' . self::escape($node['pageUrl']) . '" title="Open page preview">' . $caption . '</a>';
        }
        if (isset($node['dialogUrl'])) {
            return '<a class="button" href="' . self::escape($node['dialogUrl']) . '" title="Open dialog preview">' . $caption . '</a>';
        }
        $note = array_key_exists('dialog', $node) ? 'Dialog preview unavailable' : 'Not executed in preview';
        return '<span class="button inert" aria-disabled="true" title="' . $note . '">' . $caption . '</span>';
    }

    /**
     * Configured caption, else the action name, else a label derived from the alias, else the widget type.
     *
     * @return string
     */
    protected function buildTextCaption(): string
    {
        $node = $this->getNode();
        $action = $node['action'] ?? null;
        return $node['caption'] ?? $node['actionName'] ?? ($action === null
            ? $node['type'] : (self::OBJECT_DIALOGS[strtolower($action)] ?? substr(strrchr('.' . $action, '.'), 1)));
    }
}
