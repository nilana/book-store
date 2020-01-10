<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\BookCategory;

class BookCategoryFixture extends Fixture
{
    public function load(ObjectManager $manager)
    {
        
        $cat = new BookCategory();
        $cat->setName('Children');
        $manager->persist($cat);

        $cat = new BookCategory();
        $cat->setName('Fiction');
        $manager->persist($cat);


        $manager->flush();
    }
}
