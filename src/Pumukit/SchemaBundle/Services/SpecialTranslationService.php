<?php

declare(strict_types=1);

namespace Pumukit\SchemaBundle\Services;

use Pumukit\SchemaBundle\Document\EmbeddedBroadcast;

class SpecialTranslationService
{
    private $translator;

    /**
     * Constructor.
     */
    public function __construct(\Symfony\Contracts\Translation\TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Get I18n EmbeddedBroadcast.
     *
     * @param string $locale
     */
    public function getI18nEmbeddedBroadcast(EmbeddedBroadcast $embeddedBroadcast, $locale = null)
    {
        $groups = $embeddedBroadcast->getGroups();
        $groupsDescription = '';
        if ((EmbeddedBroadcast::TYPE_GROUPS === $embeddedBroadcast->getType()) && $groups) {
            $groupsDescription = ': ';
            foreach ($groups as $group) {
                $groupsDescription .= $group->getName();
                if ($group != $groups->last()) {
                    $groupsDescription .= ', ';
                }
            }
        }

        if ($locale) {
            return $this->translator->trans($embeddedBroadcast->getName(), [], null, $locale).$groupsDescription;
        }

        return $this->translator->trans($embeddedBroadcast->getName()).$groupsDescription;
    }
}
