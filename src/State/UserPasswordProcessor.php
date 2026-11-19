<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserPasswordProcessor implements ProcessorInterface
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $userPasswordHasher,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        if(!$data instanceof User) {
            throw new \LogicException('Expected a User instance.');
        }

        if ([] === $data->getRoles()){
            throw new UnprocessableEntityHttpException('A user must have at least one role.');
        };

        $plainPassword = $data->getPlainPassword();

        if (null !== $plainPassword && '' !== $plainPassword) {
            $data->setPassword($this->userPasswordHasher->hashPassword($data, $plainPassword));
            $data->eraseCredentials();
        }elseif ($operation instanceof Post){
            throw new BadRequestHttpException('The password is required.');
        }

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;

    }
}
