<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Book;
use App\Entity\BookCategory;
//use Faker\Generator as Faker;

class BookFixture extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $faker = \Faker\Factory::create();//new Faker();
        //$faker->addProvider(new \Faker\Provider\Book($faker));
        for($i=0; $i<100; $i++) { // creating 100 records
            $book = new Book();
            $book->setName($faker->sentence(rand(2,6)));
            $book->setAuthor($faker->name);
            $book->setPrice($faker->randomNumber(2));
            $book->setDescription($faker->text);
            $bookCategories = $manager->getRepository(BookCategory::class)->findAll();
            shuffle($bookCategories);
            $book->setCategory($bookCategories[0]);
            $book->setIsbn($faker->ean13());
            $manager->persist($book);
        }
        $manager->flush();
    }
}
