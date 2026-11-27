<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/** Keeps the configured panel arrangement without enabling resizing or other live layout behavior. */
class WireframeSplit extends WireframeBasicElement
{
    protected const CHILD_TYPES = parent::CHILD_TYPES + ['panels' => 'Panel'];

    /**
     * Recognize both the dedicated horizontal widget and a split configured as horizontal.
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeBasicElement::projectDetails()
     */
    public function projectDetails(array $config, callable $resolvePageUrl, callable $registerDialog): array
    {
        $type = strtolower(preg_replace('/^exface\.Core\./i', '', $this->getNode()['type']));
        return $type === 'splithorizontal' || ($type === 'split' && ($config['orientation'] ?? null) === 'horizontal')
            ? ['horizontal' => true] : [];
    }

    /**
     * Core treats `widgets` on a split as panels too, so both groups follow the orientation.
     *
     * {@inheritDoc}
     * @see \axenox\GenAI\Facades\Wireframe\Elements\WireframeBasicElement::buildCssChildGroupClass()
     */
    protected function buildCssChildGroupClass(string $group): string
    {
        return in_array($group, ['panels', 'widgets'], true) && ! empty($this->getNode()['horizontal']) ? 'group row' : 'group';
    }
}
