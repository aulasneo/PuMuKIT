<?php

namespace Pumukit\SchemaBundle\Tests\Repository;

use Pumukit\CoreBundle\Tests\PumukitTestCase;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Tag;

/**
 * @internal
 * @coversNothing
 */
class EmbedRelationsTest extends PumukitTestCase
{
    private $repoMmobjs;
    private $repoTags;

    public function setUp(): void
    {
        $options = ['environment' => 'test'];
        static::bootKernel($options);
        parent::setUp();
        $this->repoMmobjs = $this->dm->getRepository(MultimediaObject::class);
        $this->repoTags = $this->dm->getRepository(Tag::class);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->dm->close();

        $this->repoMmobjs = null;
        $this->repoTags = null;
        gc_collect_cycles();
    }

    public function testRepositoryEmpty(): void
    {
        $this->assertCount(0, $this->repoMmobjs->findAll());
        $this->assertCount(0, $this->repoTags->findAll());
    }

    public function testCreateRootTag(): void
    {
        $tag = new Tag();
        $tag->setCod('ROOT');

        $this->dm->persist($tag);
        $this->dm->flush();

        // This should pass to check the unrequired fields
        $this->assertCount(1, $this->repoTags->findAll());
    }

    public function testGetChildren(): void
    {
        $this->createTestTree();

        $tag = $this->repoTags->findOneByCod('ROOT');
        $tree = $this->repoTags->getTree($tag);
        $this->assertCount(6, $tree);
        $children = $this->repoTags->getChildren($tag);
        $this->assertCount(5, $children);
        $this->assertEquals(5, $this->repoTags->childCount($tag));
        $directChildren = $this->repoTags->getChildren($tag, true);
        $this->assertCount(2, $directChildren);

        $tag = $this->repoTags->findOneByCod('B');
        $tree = $this->repoTags->getTree($tag);
        $this->assertCount(4, $tree);
        $children = $this->repoTags->getChildren($tag);
        $this->assertCount(3, $children);
        $this->assertEquals(3, $this->repoTags->childCount($tag));
        $directChildren = $this->repoTags->getChildren($tag, true);
        $this->assertCount(2, $directChildren);
    }

    public function testGetRootNodes(): void
    {
        $this->createTestTree();

        $tree = $this->repoTags->getRootNodes();
        $this->assertCount(1, $tree);
    }

    public function testTagEmptyInMultimediaObject(): void
    {
        $this->createTestMultimediaObject();

        $this->assertCount(0, $this->repoMmobjs->findOneByDuration(300)->getTags());
    }

    public function testAddTagToMultimediaObject(): void
    {
        $this->createTestTree();
        $this->createTestMultimediaObject();
        $this->addTagToMultimediaObject();

        $this->assertCount(1, $this->repoMmobjs->findOneByDuration(300)->getTags());
        $this->assertEquals('B2A', $this->repoTags->findOneByCod('B2A')->getCod());
    }

    public function testAddAndRemoveTagToMultimediaObject(): void
    {
        $this->createTestTree();
        $this->createTestMultimediaObject();
        $this->addTagToMultimediaObject();
        $this->removeTagFromMultimediaObject();

        $this->assertCount(0, $this->repoMmobjs->findOneByDuration(300)->getTags());
        $this->assertEquals('B2A', $this->repoTags->findOneByCod('B2A')->getCod());
    }

    private function createTestTree(): void
    {
        $tag = new Tag();
        $tag->setCod('ROOT');

        $this->dm->persist($tag);
        $this->dm->flush();

        $tagA = new Tag();
        $tagA->setCod('A');
        $tagA->setParent($tag);
        $this->dm->persist($tagA);

        $tagB = new Tag();
        $tagB->setCod('B');
        $tagB->setParent($tag);
        $this->dm->persist($tagB);

        $tagB1 = new Tag();
        $tagB1->setCod('B1');
        $tagB1->setParent($tagB);
        $this->dm->persist($tagB1);

        $tagB2 = new Tag();
        $tagB2->setCod('B2');
        $tagB2->setParent($tagB);
        $this->dm->persist($tagB2);

        $tagB2A = new Tag();
        $tagB2A->setCod('B2A');
        $tagB2A->setParent($tagB2);
        $this->dm->persist($tagB2A);

        $this->dm->flush();
    }

    private function createTestMultimediaObject(): void
    {
        $status = MultimediaObject::STATUS_PUBLISHED;
        $record_date = new \DateTime();
        $public_date = new \DateTime();
        $title = 'titulo cualquiera';
        $subtitle = 'Subtitle paragraph';
        $description = 'Description text';
        $duration = 300;

        $mmobj = new MultimediaObject();
        $mmobj->setStatus($status);
        $mmobj->setRecordDate($record_date);
        $mmobj->setPublicDate($public_date);
        $mmobj->setTitle($title);
        $mmobj->setSubtitle($subtitle);
        $mmobj->setDescription($description);
        $mmobj->setDuration($duration);

        $this->dm->persist($mmobj);
        $this->dm->flush();
    }

    private function addTagToMultimediaObject(): void
    {
        $tagB2A = $this->repoTags->findOneByCod('B2A');
        $mmobj = $this->repoMmobjs->findOneByDuration(300);

        $mmobj->addTag($tagB2A);
        $this->dm->persist($mmobj);
        $this->dm->flush();
    }

    private function removeTagFromMultimediaObject(): void
    {
        $tagB2A = $this->repoTags->findOneByCod('B2A');
        $mmobj = $this->repoMmobjs->findOneByDuration(300);

        $hasRemoved = $mmobj->removeTag($tagB2A);

        $this->dm->persist($mmobj);
        $this->dm->flush();
    }
}
