<?php

declare(strict_types=1);

namespace Pumukit\NewAdminBundle\Form\Type\Base;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Intl\Languages;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomLanguageType extends AbstractType
{
    public static $addonLanguages = [
        'lse' => 'Spanish Sign Language',
        'ssp' => 'Spanish Sign Language',
        'lsi' => 'International Sign Language',
        'sgn' => 'International Sign Language',
        'val' => 'Valencian',
    ];

    private $translator;
    private $customLanguages;

    public function __construct(\Symfony\Contracts\Translation\TranslatorInterface $translator, array $customLanguages = [])
    {
        $this->translator = $translator;
        $this->customLanguages = $customLanguages;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'choices' => array_flip(self::getLanguageNames($this->customLanguages, $this->translator)),
            ]
        );
    }

    public static function getLanguageNames($customLanguages, $translator)
    {
        $languageNames = Languages::getNames();

        if ($customLanguages) {
            $choices = [];
            foreach ($customLanguages as $aux) {
                $code = strtolower($aux);
                $choices[$code] = $languageNames[$code] ??
                    (isset(self::$addonLanguages[$code]) ? $translator->trans(self::$addonLanguages[$code]) : $code);
            }
        } else {
            $choices = $languageNames;
        }

        return $choices;
    }

    public function getParent()
    {
        return ChoiceType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'customlanguage';
    }
}
