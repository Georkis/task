<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->createUser('admin@taskmanager.local', 'Admin User', [User::ROLE_ADMIN], 'admin1234');
        $workerOne = $this->createUser('worker1@taskmanager.local', 'Alice Worker', [User::ROLE_WORKER], 'worker1234');
        $workerTwo = $this->createUser('worker2@taskmanager.local', 'Bob Worker', [User::ROLE_WORKER], 'worker1234');

        $manager->persist($admin);
        $manager->persist($workerOne);
        $manager->persist($workerTwo);

        $now = new \DateTimeImmutable();

        $manager->flush();
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $email, string $name, array $roles, string $plainPassword): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setName($name)
            ->setRoles($roles);

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        return $user;
    }
}
