<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityTest extends WebTestCase
{
    public function testTaskCollectionRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/tasks', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCurrentUserEndpointRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/me', server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testDocumentationIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/docs');

        self::assertResponseIsSuccessful();
    }
}
