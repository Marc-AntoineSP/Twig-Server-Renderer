<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class DraftType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('revision', HiddenType::class, ['constraints' => [new Assert\Regex('/^[1-9][0-9]{0,8}$/D')]])
            ->add('title', TextType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 120)]])
            ->add('description', TextareaType::class, ['required' => false, 'empty_data' => '', 'constraints' => [new Assert\Length(max: 2000)]])
            ->add('tagsText', TextType::class, ['label' => 'Tags', 'help' => 'Separate tags with commas.', 'required' => false, 'empty_data' => '', 'constraints' => [new Assert\Length(max: 1000)]])
            ->add('manifestJson', TextareaType::class, ['label' => 'Manifest JSON', 'help' => 'File checksums are regenerated on save. Name and kind must stay unchanged.', 'attr' => ['rows' => 22, 'spellcheck' => 'false', 'class' => 'font-mono text-sm'], 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 262144)]])
            ->add('sourceMapJson', TextareaType::class, ['label' => 'Source files JSON', 'help' => 'An object mapping each portable file path to its text. Source is stored as data; it is never executed by the studio.', 'attr' => ['rows' => 24, 'spellcheck' => 'false', 'class' => 'font-mono text-sm'], 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 3145728)]]);
    }
}
