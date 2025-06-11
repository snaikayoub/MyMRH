<?php

namespace App\Controller\Admin;

use App\Entity\GrpPerf;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;

class GrpPerfCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return GrpPerf::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Group Performance')
            ->setEntityLabelInPlural('Group Performances')
            ->setSearchFields(['nameGrp'])
            ->setDefaultSort(['nameGrp' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->hideOnForm(),

            TextField::new('nameGrp')
                ->setLabel('Group Name')
                ->setRequired(true)
                ->setMaxLength(255)
                ->setHelp('Enter the group performance name'),

            AssociationField::new('employees')
                ->setLabel('Employees')
                ->hideOnForm()
                ->hideOnIndex()
                ->onlyOnDetail()
                ->formatValue(function ($value, $entity) {
                    return $value ? count($value) . ' employee(s)' : '0 employees';
                }),

            AssociationField::new('categoryTMs')
                ->setLabel('Category TMs')
                ->hideOnForm()
                ->hideOnIndex()
                ->onlyOnDetail()
                ->formatValue(function ($value, $entity) {
                    return $value ? count($value) . ' category TM(s)' : '0 category TMs';
                })
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('nameGrp');
    }
}