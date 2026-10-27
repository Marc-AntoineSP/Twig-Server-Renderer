<?php

namespace App\Form;

use Styleguide\Contracts\ThemeCssGenerator;
use Styleguide\Contracts\ThemeValidator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ThemeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $path = static fn (string $name, string $type = 'string'): array => ['data-theme-path' => $name, 'data-theme-type' => $type];
        $builder->add('revision', HiddenType::class, ['mapped' => false, 'data' => $options['revision']]);
        $builder->add('name', TextType::class, ['label' => 'Theme name (namespace/slug)', 'disabled' => $options['existing'], 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100), new Assert\Regex('/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/D')], 'attr' => $path('name')]);
        $builder->add('cssName', TextType::class, ['label' => 'CSS theme name', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100), new Assert\Regex('/^[a-z][a-z0-9-]*$/D')], 'attr' => $path('cssName')]);
        $builder->add('version', TextType::class, ['label' => 'Proposed release version', 'constraints' => [new Assert\Regex('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D'), new Assert\Length(max: 32)], 'attr' => $path('version')]);
        $builder->add('colorScheme', ChoiceType::class, ['label' => 'Colour scheme', 'choices' => ['Light' => 'light', 'Dark' => 'dark'], 'attr' => $path('colorScheme')]);
        $builder->add('default', CheckboxType::class, ['label' => 'Default theme', 'required' => false, 'attr' => $path('default', 'boolean')]);
        $builder->add('prefersDark', CheckboxType::class, ['label' => 'Prefer for dark mode', 'required' => false, 'attr' => $path('prefersDark', 'boolean')]);
        $colors = $builder->create('colors', FormType::class, ['label' => 'Semantic colours']);
        foreach (ThemeValidator::COLORS as $color) {
            $colors->add($color, TextType::class, ['label' => $color, 'constraints' => [new Assert\Regex('/^#[0-9A-Fa-f]{6}$/D')], 'attr' => [...$path('colors.'.$color), 'maxlength' => 7, 'pattern' => '#[0-9A-Fa-f]{6}']]);
        }
        $builder->add($colors);
        $geometry = $builder->create('geometry', FormType::class, ['label' => 'Geometry']);
        foreach (ThemeCssGenerator::GEOMETRY as $key => [$label, $unit]) {
            $max = str_starts_with($key, 'size') || in_array($key, ['depth', 'noise'], true) ? 1 : 4;
            $min = str_starts_with($key, 'size') ? 0.125 : 0;
            $geometry->add($key, in_array($key, ['borderPx', 'depth', 'noise'], true) ? IntegerType::class : NumberType::class, [...(in_array($key, ['borderPx', 'depth', 'noise'], true) ? [] : ['html5' => true]), 'label' => $label.('' !== $unit ? ' ('.$unit.')' : ''), 'constraints' => [new Assert\Range(min: $min, max: $max)], 'attr' => [...$path('geometry.'.$key, 'number'), 'min' => $min, 'max' => $max, 'step' => in_array($key, ['borderPx', 'depth', 'noise'], true) ? 1 : .125]]);
        }
        $builder->add($geometry);
        $typography = $builder->create('typography', FormType::class, ['label' => 'Typography']);
        foreach (['bodyFontId' => 'Theme body font', 'headingFontId' => 'Theme heading font'] as $key => $label) {
            $typography->add($key, ChoiceType::class, ['label' => $label, 'choices' => array_combine($options['font_ids'], $options['font_ids']), 'attr' => $path('typography.'.$key)]);
        }
        $typography->add('textClass', ChoiceType::class, ['label' => 'Theme text size', 'choices' => array_combine(['text-xs', 'text-sm', 'text-base', 'text-lg', 'text-xl'], ['text-xs', 'text-sm', 'text-base', 'text-lg', 'text-xl']), 'attr' => $path('typography.textClass')]);
        $typography->add('rootFontSizePx', IntegerType::class, ['label' => 'Theme root size (px)', 'constraints' => [new Assert\Range(min: 12, max: 24)], 'attr' => [...$path('typography.rootFontSizePx', 'number'), 'min' => 12, 'max' => 24]]);
        $typography->add('lineHeight', NumberType::class, ['label' => 'Line height', 'html5' => true, 'constraints' => [new Assert\Range(min: 1, max: 2)], 'attr' => [...$path('typography.lineHeight', 'number'), 'min' => 1, 'max' => 2, 'step' => .1]]);
        $builder->add($typography);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['existing' => false, 'revision' => 0, 'font_ids' => ['system-sans', 'system-serif', 'system-mono']]);
    }
}
