<?php
namespace axenox\GenAI\Facades\Wireframe;

/**
 * Encodes, validates, and links dialog previews. A dialog link lists the buttons followed from the
 * authorized page, one `dialogStep` per opened dialog, so a link can only reach dialogs inside that page.
 * The number of steps is limited because dialog cycles (an editor linking back to itself) would
 * otherwise let a link grow without end.
 */
final class WireframeDialogPages
{
    public const MAX_STEPS = 20;
    private const MAX_LENGTH = 2000;
    private const STEP_SEPARATOR = ';';
    /** `self` for the view's root, otherwise `group/index` pairs as produced by UiWireframeProjector. */
    private const STEP_PATTERN = '~^(?:self|[a-z][a-z0-9_]*/\d{1,4}(?:/[a-z][a-z0-9_]*/\d{1,4})*)$~iD';

    /**
     * Parse the `dialog` query value.
     *
     * @param mixed $value
     * @return string[]|null Steps, `[]` without a value, or null if the value is malformed.
     */
    public static function parse(mixed $value): ?array
    {
        if ($value === null) {
            return [];
        }
        if (! is_string($value) || $value === '' || strlen($value) > self::MAX_LENGTH) {
            return null;
        }
        $steps = explode(self::STEP_SEPARATOR, $value);
        if (count($steps) > self::MAX_STEPS) {
            return null;
        }
        foreach ($steps as $step) {
            if (! preg_match(self::STEP_PATTERN, $step)) {
                return null;
            }
        }
        return $steps;
    }

    /**
     * @param string $pageUrl
     * @param string[] $steps
     * @return string
     */
    public static function buildUrl(string $pageUrl, array $steps): string
    {
        return $steps === [] ? $pageUrl : $pageUrl . '?dialog=' . rawurlencode(implode(self::STEP_SEPARATOR, $steps));
    }

    /**
     * Give every button in the current view that opens a dialog a link one step deeper; at the step limit
     * the button stays unlinked.
     *
     * @param array $node
     * @param string $pageUrl
     * @param string[] $currentSteps Steps leading to the current view.
     * @return void
     */
    public static function link(array &$node, string $pageUrl, array $currentSteps): void
    {
        if (isset($node['dialogStep']) && count($currentSteps) < self::MAX_STEPS) {
            $node['dialogUrl'] = self::buildUrl($pageUrl, [...$currentSteps, $node['dialogStep']]);
        }
        foreach ($node['children'] as $group => $children) {
            foreach (array_keys($children) as $index) {
                self::link($node['children'][$group][$index], $pageUrl, $currentSteps);
            }
        }
    }
}
