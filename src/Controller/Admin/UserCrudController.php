<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield EmailField::new('email');
        yield TextField::new('fullName', 'Nom complet');
        yield ChoiceField::new('roles')
            ->setChoices([
                'Administrateur'           => 'ROLE_ADMIN',
                'RH'                       => 'ROLE_RH',
                'Responsable Service'      => 'ROLE_RESPONSABLE_SERVICE',
                'Responsable Division'     => 'ROLE_RESPONSABLE_DIVISION',
                'Gestionnaire'             => 'ROLE_GESTIONNAIRE',
                'Collaborateur'            => 'ROLE_COLLABORATEUR',
            ])
            ->allowMultipleChoices()
            ->renderAsBadges();

        // associations métier
        yield AssociationField::new('managedServices',      'Services gérés')
            ->setCrudController(ServiceCrudController::class)
            ->onlyOnForms();
        yield AssociationField::new('validatedServices',   'Services à valider')
            ->setCrudController(ServiceCrudController::class)
            ->onlyOnForms();
        yield AssociationField::new('validatedDivisions',  'Divisions à valider')
            ->setCrudController(DivisionCrudController::class)
            ->onlyOnForms();

        // Sur les pages list/detail, on peut afficher en read-only
        yield ArrayField::new('managedServices')->onlyOnIndex();
        yield ArrayField::new('validatedServices')->onlyOnIndex();
        yield ArrayField::new('validatedDivisions')->onlyOnIndex();
    }
}
