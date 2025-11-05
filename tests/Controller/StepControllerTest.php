<?php

namespace App\Tests\Controller;

use App\Entity\Step;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StepControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $stepRepository;
    private string $path = '/step/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->stepRepository = $this->manager->getRepository(Step::class);

        foreach ($this->stepRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Step index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first()->text());
    }

    public function testNew(): void
    {
        $this->markTestIncomplete();
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'step[position]' => 'Testing',
            'step[content]' => 'Testing',
            'step[durationMinutes]' => 'Testing',
            'step[recipe]' => 'Testing',
        ]);

        self::assertResponseRedirects($this->path);

        self::assertSame(1, $this->stepRepository->count([]));
    }

    public function testShow(): void
    {
        $this->markTestIncomplete();
        $fixture = new Step();
        $fixture->setPosition('My Title');
        $fixture->setContent('My Title');
        $fixture->setDurationMinutes('My Title');
        $fixture->setRecipe('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Step');

        // Use assertions to check that the properties are properly displayed.
    }

    public function testEdit(): void
    {
        $this->markTestIncomplete();
        $fixture = new Step();
        $fixture->setPosition('Value');
        $fixture->setContent('Value');
        $fixture->setDurationMinutes('Value');
        $fixture->setRecipe('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'step[position]' => 'Something New',
            'step[content]' => 'Something New',
            'step[durationMinutes]' => 'Something New',
            'step[recipe]' => 'Something New',
        ]);

        self::assertResponseRedirects('/step/');

        $fixture = $this->stepRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getPosition());
        self::assertSame('Something New', $fixture[0]->getContent());
        self::assertSame('Something New', $fixture[0]->getDurationMinutes());
        self::assertSame('Something New', $fixture[0]->getRecipe());
    }

    public function testRemove(): void
    {
        $this->markTestIncomplete();
        $fixture = new Step();
        $fixture->setPosition('Value');
        $fixture->setContent('Value');
        $fixture->setDurationMinutes('Value');
        $fixture->setRecipe('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/step/');
        self::assertSame(0, $this->stepRepository->count([]));
    }
}
