<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class NewItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Name (namespace/slug)', 'help' => 'Lowercase letters, numbers and hyphens. Names are immutable.', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100), new Assert\Regex('/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/D')]])
            ->add('kind', ChoiceType::class, ['choices' => ['Component' => 'component', 'Block' => 'block', 'Layout' => 'layout']])
            ->add('title', TextType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 120)]])
            ->add('description', TextareaType::class, ['required' => false, 'empty_data' => '', 'constraints' => [new Assert\Length(max: 2000)]])
            ->add('tagsText', TextType::class, ['label' => 'Tags', 'help' => 'Separate tags with commas.', 'required' => false, 'empty_data' => '', 'constraints' => [new Assert\Length(max: 1000)]]);
    }
}
