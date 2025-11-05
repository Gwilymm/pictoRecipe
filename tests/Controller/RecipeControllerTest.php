<?php

namespace App\Tests\Controller;

use App\Entity\Recipe;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RecipeControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private EntityRepository $recipeRepository;
    private string $path = '/recipe/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->recipeRepository = $this->manager->getRepository(Recipe::class);

        foreach ($this->recipeRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Recipe index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first()->text());
    }

    public function testNew(): void
    {
        $this->markTestIncomplete();
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'recipe[title]' => 'Testing',
            'recipe[description]' => 'Testing',
            'recipe[servings]' => 'Testing',
            'recipe[prepTimeMinutes]' => 'Testing',
            'recipe[cookTimeMinutes]' => 'Testing',
            'recipe[createdAt]' => 'Testing',
            'recipe[updatedAt]' => 'Testing',
        ]);

        self::assertResponseRedirects($this->path);

        self::assertSame(1, $this->recipeRepository->count([]));
    }

    public function testShow(): void
    {
        $this->markTestIncomplete();
        $fixture = new Recipe();
        $fixture->setTitle('My Title');
        $fixture->setDescription('My Title');
        $fixture->setServings('My Title');
        $fixture->setPrepTimeMinutes('My Title');
        $fixture->setCookTimeMinutes('My Title');
        $fixture->setCreatedAt('My Title');
        $fixture->setUpdatedAt('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Recipe');

        // Use assertions to check that the properties are properly displayed.
    }

    public function testEdit(): void
    {
        $this->markTestIncomplete();
        $fixture = new Recipe();
        $fixture->setTitle('Value');
        $fixture->setDescription('Value');
        $fixture->setServings('Value');
        $fixture->setPrepTimeMinutes('Value');
        $fixture->setCookTimeMinutes('Value');
        $fixture->setCreatedAt('Value');
        $fixture->setUpdatedAt('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'recipe[title]' => 'Something New',
            'recipe[description]' => 'Something New',
            'recipe[servings]' => 'Something New',
            'recipe[prepTimeMinutes]' => 'Something New',
            'recipe[cookTimeMinutes]' => 'Something New',
            'recipe[createdAt]' => 'Something New',
            'recipe[updatedAt]' => 'Something New',
        ]);

        self::assertResponseRedirects('/recipe/');

        $fixture = $this->recipeRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getTitle());
        self::assertSame('Something New', $fixture[0]->getDescription());
        self::assertSame('Something New', $fixture[0]->getServings());
        self::assertSame('Something New', $fixture[0]->getPrepTimeMinutes());
        self::assertSame('Something New', $fixture[0]->getCookTimeMinutes());
        self::assertSame('Something New', $fixture[0]->getCreatedAt());
        self::assertSame('Something New', $fixture[0]->getUpdatedAt());
    }

    public function testRemove(): void
    {
        $this->markTestIncomplete();
        $fixture = new Recipe();
        $fixture->setTitle('Value');
        $fixture->setDescription('Value');
        $fixture->setServings('Value');
        $fixture->setPrepTimeMinutes('Value');
        $fixture->setCookTimeMinutes('Value');
        $fixture->setCreatedAt('Value');
        $fixture->setUpdatedAt('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/recipe/');
        self::assertSame(0, $this->recipeRepository->count([]));
    }
}
