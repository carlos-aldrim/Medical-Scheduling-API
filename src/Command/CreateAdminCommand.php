<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin')]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = 'admin@hospital.com';

        if ($this->userRepository->findOneBy(['email' => $email])) {
            $output->writeln('Admin já existe, pulando.');
            return Command::SUCCESS;
        }

        $user = new User();
        $user->setName('Administrador');
        $user->setEmail($email);
        $user->setRoles([User::ROLE_ADMIN]);
        $user->setPassword($this->hasher->hashPassword($user, 'Admin@123'));

        $this->em->persist($user);
        $this->em->flush();

        $output->writeln('✅ Admin criado: admin@hospital.com / Admin@123');

        return Command::SUCCESS;
    }
}
