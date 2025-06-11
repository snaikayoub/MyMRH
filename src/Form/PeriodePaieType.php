<?php
// src/Form/PeriodePaieType.php
namespace App\Form;

use App\Entity\PeriodePaie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PeriodePaieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('type_paie', ChoiceType::class, [
                'choices'  => [
                    'Mensuelle'  => 'mensuelle',
                    'Quinzaine'  => 'quinzaine',
                ],
                'label'    => 'Type de paie',
            ])
            ->add('mois', IntegerType::class, [
                'label' => 'Mois (1–12)',
            ])
            ->add('annee', IntegerType::class, [
                'label' => 'Année',
            ])
            ->add('quinzaine', ChoiceType::class, [
                'choices'  => [
                    '—'        => null,
                    '1re'      => 1,
                    '2nde'     => 2,
                ],
                'label'    => 'Quinzaine (si applicable)',
                'required' => false,
            ])
            ->add('statut', ChoiceType::class, [
                'choices'  => [
                    'En attente' => 'en_attente',
                    'Clôturée'   => 'cloturee',
                ],
                'label'    => 'Statut',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => PeriodePaie::class,
        ]);
    }
}
