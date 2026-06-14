<?php

namespace App\Tests\Api;

use App\Entity\Doctor;
use App\Entity\Patient;
use App\Entity\Specialty;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $browser;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->browser = static::createClient();

        $container = static::getContainer();
        $this->em  = $container->get(EntityManagerInterface::class);

        $this->truncateDatabase();
    }

    protected function jsonRequest(
        string  $method,
        string  $uri,
        ?array  $body    = null,
        ?string $token   = null,
        array   $headers = [],
    ): void {
        $serverHeaders = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        if ($token !== null) {
            $serverHeaders['HTTP_AUTHORIZATION'] = "Bearer {$token}";
        }

        foreach ($headers as $k => $v) {
            $serverHeaders['HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
        }

        $this->browser->request(
            $method,
            $uri,
            [],
            [],
            $serverHeaders,
            $body !== null ? json_encode($body, JSON_THROW_ON_ERROR) : null,
        );
    }

    protected function responseJson(): array
    {
        $content = $this->browser->getResponse()->getContent();
        return json_decode($content ?: '{}', true, 512, JSON_THROW_ON_ERROR);
    }

    protected function responseStatus(): int
    {
        return $this->browser->getResponse()->getStatusCode();
    }

    protected function getToken(string $email, string $password): string
    {
        $this->jsonRequest('POST', '/auth/login', [
            'email'    => $email,
            'password' => $password,
        ]);

        $data = $this->responseJson();
        self::assertArrayHasKey('token', $data, 'Login did not return a token');

        return $data['token'];
    }

    protected function createUser(
        string   $email,
        string   $password,
        UserRole $role = UserRole::Receptionist,
    ): User {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setName('Test User');
        $user->setRoles([$role->value]);
        $user->setPassword($hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function createAdminUser(
        string $email    = 'admin@test.dev',
        string $password = 'Admin@123',
    ): User {
        return $this->createUser($email, $password, UserRole::Admin);
    }

    protected function createReceptionistUser(
        string $email    = 'reception@test.dev',
        string $password = 'Reception@123',
    ): User {
        return $this->createUser($email, $password, UserRole::Receptionist);
    }

    protected function createSpecialty(
        string $name        = 'Cardiology',
        string $description = 'Heart specialist',
    ): Specialty {
        $specialty = new Specialty();
        $specialty->setName($name);
        $specialty->setDescription($description);

        $this->em->persist($specialty);
        $this->em->flush();

        return $specialty;
    }

    protected function createDoctor(
        Specialty $specialty,
        string    $name   = 'Dr. Test',
        string    $crm    = 'CRM-SP-12345',
        bool      $active = true,
        int       $maxPerDay = 10,
    ): Doctor {
        $doctor = new Doctor();
        $doctor->setName($name);
        $doctor->setCrm($crm);
        $doctor->setSpecialty($specialty);
        $doctor->setActive($active);
        $doctor->setMaxAppointmentsPerDay($maxPerDay);

        $this->em->persist($doctor);
        $this->em->flush();

        return $doctor;
    }

    protected function createPatient(
        string $name      = 'Jane Doe',
        string $cpf       = '52998224725',
        bool   $active    = true,
    ): Patient {
        $patient = new Patient();
        $patient->setName($name);
        $patient->setCpf($cpf);
        $patient->setBirthDate(new \DateTime('1990-06-15'));
        $patient->setActive($active);

        $this->em->persist($patient);
        $this->em->flush();

        return $patient;
    }

    private function truncateDatabase(): void
    {
        $connection = $this->em->getConnection();
        $platform   = $connection->getDatabasePlatform();

        $tables = ['appointment', 'doctor', 'patient', 'specialty', '"user"'];

        $connection->executeStatement('SET session_replication_role = replica');
        foreach ($tables as $table) {
            $connection->executeStatement("TRUNCATE TABLE {$table} RESTART IDENTITY CASCADE");
        }
        $connection->executeStatement('SET session_replication_role = DEFAULT');
    }
}
