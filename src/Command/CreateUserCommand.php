<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Créer un utilisateur test',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->setHelp('This command creates test users with roles: ROLE_USER, ROLE_GEST, ROLE_ADMIN');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = new User();
        $user->setEmail('user@ocp.com');
        $user->setRoles(['ROLE_USER']);
        $user->setFullName('Utilisateur');
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, 'user')
        );

        $this->em->persist($user);

        $user = new User();
        $user->setEmail('gest@ocp.com');
        $user->setRoles(['ROLE_GEST']);
        $user->setFullName('Gest');
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, 'gest')
        );

        $this->em->persist($user);

        $user = new User();
        $user->setEmail('admin@ocp.com');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setFullName('Admin');
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, 'admin')
        );

        $this->em->persist($user);
        $this->em->flush();

        $output->writeln('✅ Utilisateurs créé avec succès.');
        return Command::SUCCESS;
    }
}
