<?php
namespace axenox\GenAI\Facades\Wireframe\Elements;

/** Lists every configured wizard step in order without step navigation. */
class WireframeWizard extends WireframeButtonContainer
{
    protected const CHILD_TYPES = parent::CHILD_TYPES + ['steps' => 'WizardStep'];
}
