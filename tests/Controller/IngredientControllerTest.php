<?php

namespace App\Tests\Controller;

use App\Entity\Ingredient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class IngredientControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $ingredientRepository;
    private string $path = '/ingredient/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->ingredientRepository = $this->manager->getRepository(Ingredient::class);

        foreach ($this->ingredientRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Ingredient index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first()->text());
    }

    public function testNew(): void
    {
        $this->markTestIncomplete();
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'ingredient[name]' => 'Testing',
            'ingredient[amount]' => 'Testing',
            'ingredient[unit]' => 'Testing',
            'ingredient[position]' => 'Testing',
            'ingredient[recipe]' => 'Testing',
        ]);

        self::assertResponseRedirects($this->path);

        self::assertSame(1, $this->ingredientRepository->count([]));
    }

    public function testShow(): void
    {
        $this->markTestIncomplete();
        $fixture = new Ingredient();
        $fixture->setName('My Title');
        $fixture->setAmount('My Title');
        $fixture->setUnit('My Title');
        $fixture->setPosition('My Title');
        $fixture->setRecipe('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Ingredient');

        // Use assertions to check that the properties are properly displayed.
    }

    public function testEdit(): void
    {
        $this->markTestIncomplete();
        $fixture = new Ingredient();
        $fixture->setName('Value');
        $fixture->setAmount('Value');
        $fixture->setUnit('Value');
        $fixture->setPosition('Value');
        $fixture->setRecipe('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'ingredient[name]' => 'Something New',
            'ingredient[amount]' => 'Something New',
            'ingredient[unit]' => 'Something New',
            'ingredient[position]' => 'Something New',
            'ingredient[recipe]' => 'Something New',
        ]);

        self::assertResponseRedirects('/ingredient/');

        $fixture = $this->ingredientRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getName());
        self::assertSame('Something New', $fixture[0]->getAmount());
        self::assertSame('Something New', $fixture[0]->getUnit());
        self::assertSame('Something New', $fixture[0]->getPosition());
        self::assertSame('Something New', $fixture[0]->getRecipe());
    }

    public function testRemove(): void
    {
        $this->markTestIncomplete();
        $fixture = new Ingredient();
        $fixture->setName('Value');
        $fixture->setAmount('Value');
        $fixture->setUnit('Value');
        $fixture->setPosition('Value');
        $fixture->setRecipe('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/ingredient/');
        self::assertSame(0, $this->ingredientRepository->count([]));
    }
}
