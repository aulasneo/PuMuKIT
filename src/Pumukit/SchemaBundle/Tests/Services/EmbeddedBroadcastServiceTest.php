<?php

declare(strict_types=1);

namespace Pumukit\SchemaBundle\Tests\Services;

use Pumukit\CoreBundle\Tests\PumukitTestCase;
use Pumukit\SchemaBundle\Document\EmbeddedBroadcast;
use Pumukit\SchemaBundle\Document\Group;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\PermissionProfile;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Services\EmbeddedBroadcastService;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * @internal
 *
 * @coversNothing
 */
class EmbeddedBroadcastServiceTest extends PumukitTestCase
{
    private $mmRepo;
    private $embeddedBroadcastService;
    private $mmsService;
    private $dispatcher;
    private $authorizationChecker;
    private $templating;
    private $router;

    public function setUp(): void
    {
        $options = ['environment' => 'test'];
        static::bootKernel($options);

        parent::setUp();

        $this->mmRepo = $this->dm->getRepository(MultimediaObject::class);
        $this->embeddedBroadcastService = static::$kernel->getContainer()->get('pumukitschema.embeddedbroadcast');
        $this->mmsService = static::$kernel->getContainer()->get('pumukitschema.multimedia_object');
        $this->dispatcher = static::$kernel->getContainer()->get('pumukitschema.multimediaobject_dispatcher');
        $this->authorizationChecker = static::$kernel->getContainer()->get('security.authorization_checker');
        $this->templating = $this->getMockBuilder(Environment::class)->disableOriginalConstructor()->getMock();

        $this->router = static::$kernel->getContainer()->get('router');
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->dm->close();

        $this->mmRepo = null;
        $this->embeddedBroadcastService = null;
        $this->mmsService = null;
        $this->dispatcher = null;
        $this->authorizationChecker = null;
        $this->templating = null;
        $this->router = null;
        gc_collect_cycles();
    }

    public function testCreateEmbeddedBroadcastByType()
    {
        $embeddedBroadcastService = new EmbeddedBroadcastService($this->dm, $this->mmsService, $this->dispatcher, $this->authorizationChecker, $this->templating, $this->router);
        $passwordBroadcast = $embeddedBroadcastService->createEmbeddedBroadcastByType(EmbeddedBroadcast::TYPE_PASSWORD);
        $ldapBroadcast = $embeddedBroadcastService->createEmbeddedBroadcastByType(EmbeddedBroadcast::TYPE_LOGIN);
        $groupsBroadcast = $embeddedBroadcastService->createEmbeddedBroadcastByType(EmbeddedBroadcast::TYPE_GROUPS);
        $publicBroadcast = $embeddedBroadcastService->createEmbeddedBroadcastByType(EmbeddedBroadcast::TYPE_PUBLIC);
        $defaultBroadcast = $embeddedBroadcastService->createEmbeddedBroadcastByType();

        static::assertEquals(EmbeddedBroadcast::TYPE_PASSWORD, $passwordBroadcast->getType());
        static::assertEquals(EmbeddedBroadcast::NAME_PASSWORD, $passwordBroadcast->getName());
        static::assertEquals(EmbeddedBroadcast::TYPE_LOGIN, $ldapBroadcast->getType());
        static::assertEquals(EmbeddedBroadcast::NAME_LOGIN, $ldapBroadcast->getName());
        static::assertEquals(EmbeddedBroadcast::TYPE_GROUPS, $groupsBroadcast->getType());
        static::assertEquals(EmbeddedBroadcast::NAME_GROUPS, $groupsBroadcast->getName());
        static::assertEquals(EmbeddedBroadcast::TYPE_PUBLIC, $publicBroadcast->getType());
        static::assertEquals(EmbeddedBroadcast::NAME_PUBLIC, $publicBroadcast->getName());
        static::assertEquals(EmbeddedBroadcast::TYPE_PUBLIC, $defaultBroadcast->getType());
        static::assertEquals(EmbeddedBroadcast::NAME_PUBLIC, $defaultBroadcast->getName());
    }

    public function testSetByType()
    {
        $mm = new MultimediaObject();
        $mm->setTitle('test');
        $this->dm->persist($mm);
        $this->dm->flush();

        $mm = $this->embeddedBroadcastService->setByType($mm, EmbeddedBroadcast::TYPE_PASSWORD);
        $mm = $this->mmRepo->find($mm->getId());

        static::assertEquals(EmbeddedBroadcast::TYPE_PASSWORD, $mm->getEmbeddedBroadcast()->getType());
        static::assertEquals(EmbeddedBroadcast::NAME_PASSWORD, $mm->getEmbeddedBroadcast()->getName());

        $mm = $this->embeddedBroadcastService->setByType($mm, EmbeddedBroadcast::TYPE_LOGIN);
        $mm = $this->mmRepo->find($mm->getId());

        static::assertEquals(EmbeddedBroadcast::TYPE_LOGIN, $mm->getEmbeddedBroadcast()->getType());
        static::assertEquals(EmbeddedBroadcast::NAME_LOGIN, $mm->getEmbeddedBroadcast()->getName());

        $mm = $this->embeddedBroadcastService->setByType($mm, EmbeddedBroadcast::TYPE_PUBLIC);
        $mm = $this->mmRepo->find($mm->getId());

        static::assertEquals(EmbeddedBroadcast::TYPE_PUBLIC, $mm->getEmbeddedBroadcast()->getType());
        static::assertEquals(EmbeddedBroadcast::NAME_PUBLIC, $mm->getEmbeddedBroadcast()->getName());

        $mm = $this->embeddedBroadcastService->setByType($mm, EmbeddedBroadcast::TYPE_GROUPS);
        $mm = $this->mmRepo->find($mm->getId());

        static::assertEquals(EmbeddedBroadcast::TYPE_GROUPS, $mm->getEmbeddedBroadcast()->getType());
        static::assertEquals(EmbeddedBroadcast::NAME_GROUPS, $mm->getEmbeddedBroadcast()->getName());

        $mm = $this->embeddedBroadcastService->setByType($mm);
        $mm = $this->mmRepo->find($mm->getId());

        static::assertEquals(EmbeddedBroadcast::TYPE_PUBLIC, $mm->getEmbeddedBroadcast()->getType());
        static::assertEquals(EmbeddedBroadcast::NAME_PUBLIC, $mm->getEmbeddedBroadcast()->getName());
    }

    public function testCloneResource()
    {
        $group1 = new Group();
        $group1->setKey('test1');
        $group1->setName('test1');

        $group2 = new Group();
        $group2->setKey('test2');
        $group2->setName('test2');

        $this->dm->persist($group1);
        $this->dm->persist($group2);
        $this->dm->flush();

        $password = 'password';

        $ldapBroadcast = new EmbeddedBroadcast();
        $ldapBroadcast->setType(EmbeddedBroadcast::TYPE_LOGIN);
        $ldapBroadcast->setName(EmbeddedBroadcast::NAME_LOGIN);
        $ldapBroadcast->setPassword($password);
        $ldapBroadcast->addGroup($group1);
        $ldapBroadcast->addGroup($group2);

        $clonedLdapBroadcast = $this->embeddedBroadcastService->cloneResource($ldapBroadcast);
        static::assertEquals($ldapBroadcast, $clonedLdapBroadcast);
    }

    public function testGetAllBroadcastTypes()
    {
        $embeddedBroadcastService = new EmbeddedBroadcastService($this->dm, $this->mmsService, $this->dispatcher, $this->authorizationChecker, $this->templating, $this->router);
        $broadcasts = [
            EmbeddedBroadcast::TYPE_PUBLIC => EmbeddedBroadcast::NAME_PUBLIC,
            EmbeddedBroadcast::TYPE_PASSWORD => EmbeddedBroadcast::NAME_PASSWORD,
            EmbeddedBroadcast::TYPE_LOGIN => EmbeddedBroadcast::NAME_LOGIN,
            EmbeddedBroadcast::TYPE_GROUPS => EmbeddedBroadcast::NAME_GROUPS,
        ];
        static::assertEquals($broadcasts, $embeddedBroadcastService->getAllTypes());
    }

    public function testCreatePublicEmbeddedBroadcast()
    {
        $embeddedBroadcastService = new EmbeddedBroadcastService($this->dm, $this->mmsService, $this->dispatcher, $this->authorizationChecker, $this->templating, $this->router);
        $publicBroadcast = $embeddedBroadcastService->createPublicEmbeddedBroadcast();
        static::assertEquals(EmbeddedBroadcast::TYPE_PUBLIC, $publicBroadcast->getType());
        static::assertEquals(EmbeddedBroadcast::NAME_PUBLIC, $publicBroadcast->getName());
    }

    public function testUpdateTypeAndName()
    {
        $multimediaObject = new MultimediaObject();
        $multimediaObject->setTitle('test');

        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        $mm = $this->embeddedBroadcastService->setByType($multimediaObject, EmbeddedBroadcast::TYPE_PASSWORD);
        $embeddedBroadcast = $mm->getEmbeddedBroadcast();

        static::assertEquals(EmbeddedBroadcast::TYPE_PASSWORD, $embeddedBroadcast->getType());
        static::assertEquals(EmbeddedBroadcast::NAME_PASSWORD, $embeddedBroadcast->getName());
        static::assertNotEquals(EmbeddedBroadcast::TYPE_LOGIN, $embeddedBroadcast->getType());
        static::assertNotEquals(EmbeddedBroadcast::NAME_LOGIN, $embeddedBroadcast->getName());

        $mm = $this->embeddedBroadcastService->updateTypeAndName(EmbeddedBroadcast::TYPE_LOGIN, $multimediaObject);

        static::assertNotEquals(EmbeddedBroadcast::TYPE_PASSWORD, $embeddedBroadcast->getType());
        static::assertNotEquals(EmbeddedBroadcast::NAME_PASSWORD, $embeddedBroadcast->getName());
        static::assertEquals(EmbeddedBroadcast::TYPE_LOGIN, $embeddedBroadcast->getType());
        static::assertEquals(EmbeddedBroadcast::NAME_LOGIN, $embeddedBroadcast->getName());
    }

    public function testUpdatePassword()
    {
        $multimediaObject = new MultimediaObject();
        $multimediaObject->setTitle('test');

        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        $mm = $this->embeddedBroadcastService->setByType($multimediaObject, EmbeddedBroadcast::TYPE_PASSWORD);
        $embeddedBroadcast = $mm->getEmbeddedBroadcast();

        static::assertNull($embeddedBroadcast->getPassword());

        $password = 'testing_password';
        $mm = $this->embeddedBroadcastService->updatePassword($password, $multimediaObject);

        static::assertEquals($password, $embeddedBroadcast->getPassword());
    }

    public function testAddGroup()
    {
        $group1 = new Group();
        $group1->setKey('key1');
        $group1->setName('name1');

        $group2 = new Group();
        $group2->setKey('key2');
        $group2->setName('name2');

        $group3 = new Group();
        $group3->setKey('key3');
        $group3->setName('name3');

        $multimediaObject = new MultimediaObject();
        $multimediaObject->setTitle('test');

        $this->dm->persist($group1);
        $this->dm->persist($group2);
        $this->dm->persist($group3);
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        $mm = $this->embeddedBroadcastService->setByType($multimediaObject, EmbeddedBroadcast::TYPE_PASSWORD);
        $embeddedBroadcast = $mm->getEmbeddedBroadcast();

        static::assertCount(0, $embeddedBroadcast->getGroups());
        static::assertFalse($embeddedBroadcast->containsGroup($group1));
        static::assertFalse($embeddedBroadcast->containsGroup($group2));
        static::assertFalse($embeddedBroadcast->containsGroup($group3));

        $this->embeddedBroadcastService->addGroup($group1, $multimediaObject);

        static::assertCount(1, $embeddedBroadcast->getGroups());
        static::assertTrue($embeddedBroadcast->containsGroup($group1));
        static::assertFalse($embeddedBroadcast->containsGroup($group2));
        static::assertFalse($embeddedBroadcast->containsGroup($group3));

        $this->embeddedBroadcastService->addGroup($group2, $multimediaObject);

        static::assertCount(2, $embeddedBroadcast->getGroups());
        static::assertTrue($embeddedBroadcast->containsGroup($group1));
        static::assertTrue($embeddedBroadcast->containsGroup($group2));
        static::assertFalse($embeddedBroadcast->containsGroup($group3));

        $this->embeddedBroadcastService->addGroup($group3, $multimediaObject);

        static::assertCount(3, $embeddedBroadcast->getGroups());
        static::assertTrue($embeddedBroadcast->containsGroup($group1));
        static::assertTrue($embeddedBroadcast->containsGroup($group2));
        static::assertTrue($embeddedBroadcast->containsGroup($group3));
    }

    public function testDeleteGroup()
    {
        $group1 = new Group();
        $group1->setKey('key1');
        $group1->setName('name1');

        $group2 = new Group();
        $group2->setKey('key2');
        $group2->setName('name2');

        $group3 = new Group();
        $group3->setKey('key3');
        $group3->setName('name3');

        $multimediaObject = new MultimediaObject();
        $multimediaObject->setTitle('test');

        $this->dm->persist($group1);
        $this->dm->persist($group2);
        $this->dm->persist($group3);
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        $mm = $this->embeddedBroadcastService->setByType($multimediaObject, EmbeddedBroadcast::TYPE_PASSWORD);
        $embeddedBroadcast = $mm->getEmbeddedBroadcast();

        static::assertCount(0, $embeddedBroadcast->getGroups());
        static::assertFalse($embeddedBroadcast->containsGroup($group1));
        static::assertFalse($embeddedBroadcast->containsGroup($group2));
        static::assertFalse($embeddedBroadcast->containsGroup($group3));

        $this->embeddedBroadcastService->addGroup($group1, $multimediaObject);

        static::assertCount(1, $embeddedBroadcast->getGroups());
        static::assertTrue($embeddedBroadcast->containsGroup($group1));
        static::assertFalse($embeddedBroadcast->containsGroup($group2));
        static::assertFalse($embeddedBroadcast->containsGroup($group3));

        $this->embeddedBroadcastService->deleteGroup($group1, $multimediaObject);

        static::assertCount(0, $embeddedBroadcast->getGroups());
        static::assertFalse($embeddedBroadcast->containsGroup($group1));
        static::assertFalse($embeddedBroadcast->containsGroup($group2));
        static::assertFalse($embeddedBroadcast->containsGroup($group3));

        $this->embeddedBroadcastService->deleteGroup($group2, $multimediaObject);

        static::assertCount(0, $embeddedBroadcast->getGroups());
        static::assertFalse($embeddedBroadcast->containsGroup($group1));
        static::assertFalse($embeddedBroadcast->containsGroup($group2));
        static::assertFalse($embeddedBroadcast->containsGroup($group3));

        $this->embeddedBroadcastService->addGroup($group3, $multimediaObject);

        static::assertCount(1, $embeddedBroadcast->getGroups());
        static::assertFalse($embeddedBroadcast->containsGroup($group1));
        static::assertFalse($embeddedBroadcast->containsGroup($group2));
        static::assertTrue($embeddedBroadcast->containsGroup($group3));

        $this->embeddedBroadcastService->deleteGroup($group1, $multimediaObject);

        static::assertCount(1, $embeddedBroadcast->getGroups());
        static::assertFalse($embeddedBroadcast->containsGroup($group1));
        static::assertFalse($embeddedBroadcast->containsGroup($group2));
        static::assertTrue($embeddedBroadcast->containsGroup($group3));

        $this->embeddedBroadcastService->deleteGroup($group3, $multimediaObject);

        static::assertCount(0, $embeddedBroadcast->getGroups());
        static::assertFalse($embeddedBroadcast->containsGroup($group1));
        static::assertFalse($embeddedBroadcast->containsGroup($group2));
        static::assertFalse($embeddedBroadcast->containsGroup($group3));
    }

    public function testIsUserRelatedToMultimediaObject()
    {
        $user = new User();
        $user->setUsername('user');
        $user->setEmail('user@mail.com');

        $mm = new MultimediaObject();
        $mm->setTitle('mm');

        $this->dm->persist($user);
        $this->dm->persist($mm);
        $this->dm->flush();

        static::assertFalse($this->embeddedBroadcastService->isUserRelatedToMultimediaObject($mm, $user));

        $owners1 = [$user->getId()];
        $mm->setProperty('owners', $owners1);
        $this->dm->persist($mm);
        $this->dm->flush();

        static::assertTrue($this->embeddedBroadcastService->isUserRelatedToMultimediaObject($mm, $user));

        $owners2 = [];
        $mm->setProperty('owners', $owners2);
        $this->dm->persist($mm);
        $this->dm->flush();

        static::assertFalse($this->embeddedBroadcastService->isUserRelatedToMultimediaObject($mm, $user));

        $group1 = new Group();
        $group1->setKey('key1');
        $group1->setName('name1');

        $group2 = new Group();
        $group2->setKey('key2');
        $group2->setName('name2');

        $group3 = new Group();
        $group3->setKey('key3');
        $group3->setName('name3');

        $this->dm->persist($group1);
        $this->dm->persist($group2);
        $this->dm->persist($group3);
        $this->dm->flush();

        $user->addGroup($group1);
        $mm->addGroup($group2);
        $this->dm->persist($user);
        $this->dm->persist($mm);
        $this->dm->flush();

        static::assertFalse($this->embeddedBroadcastService->isUserRelatedToMultimediaObject($mm, $user));

        $user->addGroup($group2);
        $this->dm->persist($user);
        $this->dm->flush();

        static::assertTrue($this->embeddedBroadcastService->isUserRelatedToMultimediaObject($mm, $user));

        $user->removeGroup($group2);
        $this->dm->persist($user);
        $this->dm->flush();

        static::assertFalse($this->embeddedBroadcastService->isUserRelatedToMultimediaObject($mm, $user));

        $embeddedBroadcast = new EmbeddedBroadcast();
        $embeddedBroadcast->setType(EmbeddedBroadcast::TYPE_GROUPS);
        $embeddedBroadcast->setName(EmbeddedBroadcast::NAME_GROUPS);
        $mm->setEmbeddedBroadcast($embeddedBroadcast);
        $this->dm->persist($mm);
        $this->dm->flush();

        static::assertFalse($this->embeddedBroadcastService->isUserRelatedToMultimediaObject($mm, $user));

        $embeddedBroadcast = $mm->getEmbeddedBroadcast();
        $embeddedBroadcast->addGroup($group3);
        $this->dm->persist($mm);
        $this->dm->flush();

        static::assertFalse($this->embeddedBroadcastService->isUserRelatedToMultimediaObject($mm, $user));

        $user->addGroup($group3);
        $this->dm->persist($user);
        $this->dm->flush();

        static::assertTrue($this->embeddedBroadcastService->isUserRelatedToMultimediaObject($mm, $user));

        $user->removeGroup($group3);
        $this->dm->persist($user);
        $this->dm->flush();

        static::assertFalse($this->embeddedBroadcastService->isUserRelatedToMultimediaObject($mm, $user));

        $user->addGroup($group2);
        $user->addGroup($group3);
        $this->dm->persist($user);
        $this->dm->flush();

        static::assertTrue($this->embeddedBroadcastService->isUserRelatedToMultimediaObject($mm, $user));

        $owners1 = [$user->getId()];
        $mm->setProperty('owners', $owners1);
        $this->dm->persist($mm);
        $this->dm->flush();

        static::assertTrue($this->embeddedBroadcastService->isUserRelatedToMultimediaObject($mm, $user));
    }

    public function testCanUserPlayMultimediaObject()
    {
        $user = new User();
        $user->setUsername('user');
        $user->setEmail('user@mail.com');

        $permissionProfile = new PermissionProfile();
        $permissionProfile->setScope(PermissionProfile::SCOPE_NONE);
        $permissionProfile->setName('permission profile');

        $mm = new MultimediaObject();
        $mm->setTitle('mm');

        $this->dm->persist($user);
        $this->dm->persist($permissionProfile);
        $this->dm->persist($mm);
        $this->dm->flush();

        // Test No EmbeddedBroadcast

        static::assertTrue($this->embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, ''));

        // Test TYPE_PUBLIC

        $embeddedBroadcast = new EmbeddedBroadcast();
        $embeddedBroadcast->setType(EmbeddedBroadcast::TYPE_PUBLIC);
        $embeddedBroadcast->setName(EmbeddedBroadcast::NAME_PUBLIC);
        $mm->setEmbeddedBroadcast($embeddedBroadcast);
        $this->dm->persist($mm);
        $this->dm->flush();

        static::assertTrue($this->embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, ''));

        // Test TYPE_LOGIN

        $embeddedBroadcast = $mm->getEmbeddedBroadcast();
        $embeddedBroadcast->setType(EmbeddedBroadcast::TYPE_LOGIN);
        $embeddedBroadcast->setName(EmbeddedBroadcast::NAME_LOGIN);
        $this->dm->persist($mm);
        $this->dm->flush();

        $authorizationChecker = $this->getMockBuilder(\Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $authorizationChecker->expects(static::any())
            ->method('isGranted')
            ->willReturn(false)
        ;

        $content = 'test';
        $templating = $this->getMockBuilder(\Twig\Environment::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $templating->expects(static::any())
            ->method('render')
            ->willReturn($content)
        ;

        $embeddedBroadcastService = new EmbeddedBroadcastService($this->dm, $this->mmsService, $this->dispatcher, $authorizationChecker, $templating, $this->router);

        $response = $embeddedBroadcastService->canUserPlayMultimediaObject($mm, null, '');
        static::assertInstanceOf(Response::class, $response);
        static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        static::assertEquals($content, $response->getContent());

        $response = $embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, '');
        static::assertInstanceOf(Response::class, $response);
        static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        static::assertEquals($content, $response->getContent());

        $authorizationChecker = $this->getMockBuilder(\Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $authorizationChecker->expects(static::any())
            ->method('isGranted')
            ->willReturn(true)
        ;

        $embeddedBroadcastService = new EmbeddedBroadcastService($this->dm, $this->mmsService, $this->dispatcher, $authorizationChecker, $templating, $this->router);

        static::assertTrue($embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, ''));

        // Test TYPE_GROUPS

        $group1 = new Group();
        $group1->setKey('key1');
        $group1->setName('name1');

        $group2 = new Group();
        $group2->setKey('key2');
        $group2->setName('name2');

        $group3 = new Group();
        $group3->setKey('key3');
        $group3->setName('name3');

        $this->dm->persist($group1);
        $this->dm->persist($group2);
        $this->dm->persist($group3);
        $this->dm->flush();

        $embeddedBroadcast = $mm->getEmbeddedBroadcast();
        $embeddedBroadcast->setType(EmbeddedBroadcast::TYPE_GROUPS);
        $embeddedBroadcast->setName(EmbeddedBroadcast::NAME_GROUPS);
        $mm->addGroup($group2);
        $user->addGroup($group1);
        $this->dm->persist($user);
        $this->dm->persist($mm);
        $this->dm->flush();

        $response = $embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, '');
        static::assertInstanceOf(Response::class, $response);
        static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        static::assertEquals($content, $response->getContent());

        $embeddedBroadcast = $mm->getEmbeddedBroadcast();
        $embeddedBroadcast->addGroup($group3);
        $this->dm->persist($mm);
        $this->dm->flush();

        $response = $embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, '');
        static::assertInstanceOf(Response::class, $response);
        static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        static::assertEquals($content, $response->getContent());

        $user->setPermissionProfile($permissionProfile);
        $this->dm->persist($user);
        $this->dm->flush();

        $response = $embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, '');
        static::assertInstanceOf(Response::class, $response);
        static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        static::assertEquals($content, $response->getContent());

        $permissionProfile->setScope(PermissionProfile::SCOPE_PERSONAL);
        $this->dm->persist($permissionProfile);
        $this->dm->flush();

        $response = $embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, '');
        static::assertInstanceOf(Response::class, $response);
        static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        static::assertEquals($content, $response->getContent());

        $permissionProfile->setScope(PermissionProfile::SCOPE_GLOBAL);
        $this->dm->persist($permissionProfile);
        $this->dm->flush();

        static::assertTrue($embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, ''));

        $permissionProfile->setScope(PermissionProfile::SCOPE_PERSONAL);
        $this->dm->persist($permissionProfile);
        $this->dm->flush();

        $response = $embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, '');
        static::assertInstanceOf(Response::class, $response);
        static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        static::assertEquals($content, $response->getContent());

        $mm->addGroup($group1);
        $this->dm->persist($mm);
        $this->dm->flush();

        static::assertTrue($embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, ''));

        $mm->removeGroup($group1);
        $mm->addGroup($group3);
        $this->dm->persist($mm);
        $this->dm->flush();

        $response = $embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, '');
        static::assertInstanceOf(Response::class, $response);
        static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        static::assertEquals($content, $response->getContent());

        $embeddedBroadcast = $mm->getEmbeddedBroadcast();
        $embeddedBroadcast->addGroup($group1);
        $this->dm->persist($mm);
        $this->dm->flush();

        static::assertTrue($embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, ''));

        $embeddedBroadcast = $mm->getEmbeddedBroadcast();
        $embeddedBroadcast->removeGroup($group1);
        $embeddedBroadcast->addGroup($group2);
        $this->dm->persist($mm);
        $this->dm->flush();

        $response = $embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, '');
        static::assertInstanceOf(Response::class, $response);
        static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        static::assertEquals($content, $response->getContent());

        $owners = [$user->getId()];
        $mm->setProperty('owners', $owners);
        $this->dm->persist($mm);
        $this->dm->flush();

        static::assertTrue($embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, ''));

        $mm->addGroup($group1);
        $this->dm->persist($mm);
        $this->dm->flush();

        static::assertTrue($embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, ''));

        $embeddedBroadcast = $mm->getEmbeddedBroadcast();
        $embeddedBroadcast->addGroup($group1);
        $this->dm->persist($mm);
        $this->dm->flush();

        static::assertTrue($embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, ''));

        // Test TYPE_PASSWORD

        $series = new Series();
        $series->setNumericalID(1);
        $series->setTitle('series');
        $this->dm->persist($series);
        $this->dm->flush();

        $embeddedBroadcast = $mm->getEmbeddedBroadcast();
        $embeddedBroadcast->setType(EmbeddedBroadcast::TYPE_PASSWORD);
        $embeddedBroadcast->setName(EmbeddedBroadcast::NAME_PASSWORD);
        $mm->setSeries($series);
        $this->dm->persist($mm);
        $this->dm->flush();

        $password = '';
        $response = $embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, $password);
        static::assertInstanceOf(Response::class, $response);
        static::assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $embeddedBroadcast = $mm->getEmbeddedBroadcast();
        $embeddedBroadcast->setPassword($password);
        $this->dm->persist($mm);
        $this->dm->flush();

        static::assertInstanceOf(Response::class, $response);
        static::assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $password = 'password';
        $response = $embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, $password);
        static::assertInstanceOf(Response::class, $response);
        static::assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $embeddedBroadcast = $mm->getEmbeddedBroadcast();
        $embeddedBroadcast->setPassword('not matching password');
        $this->dm->persist($mm);
        $this->dm->flush();

        $response = $embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, $password);
        static::assertInstanceOf(Response::class, $response);
        static::assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $embeddedBroadcast = $mm->getEmbeddedBroadcast();
        $embeddedBroadcast->setPassword($password);
        $this->dm->persist($mm);
        $this->dm->flush();

        static::assertTrue($embeddedBroadcastService->canUserPlayMultimediaObject($mm, $user, $password));
    }

    public function testDeleteAllFromGroup()
    {
        $group = new Group();
        $group->setKey('key');
        $group->setName('group');
        $this->dm->persist($group);
        $this->dm->flush();

        static::assertCount(0, $this->mmRepo->findWithGroupInEmbeddedBroadcast($group)->toArray());

        $mm1 = new MultimediaObject();
        $mm1->setNumericalID(1);
        $mm1->setTitle('mm1');
        $emb1 = new EmbeddedBroadcast();
        $emb1->addGroup($group);
        $mm1->setEmbeddedBroadcast($emb1);

        $mm2 = new MultimediaObject();
        $mm2->setNumericalID(2);
        $mm2->setTitle('mm2');
        $emb2 = new EmbeddedBroadcast();
        $emb2->addGroup($group);
        $mm2->setEmbeddedBroadcast($emb2);

        $mm3 = new MultimediaObject();
        $mm3->setNumericalID(3);
        $mm3->setTitle('mm3');
        $mm3->addGroup($group);
        $emb3 = new EmbeddedBroadcast();
        $emb3->addGroup($group);
        $mm3->setEmbeddedBroadcast($emb3);

        $this->dm->persist($mm1);
        $this->dm->persist($mm2);
        $this->dm->persist($mm3);
        $this->dm->flush();

        static::assertCount(3, $this->mmRepo->findWithGroupInEmbeddedBroadcast($group)->toArray());

        $this->embeddedBroadcastService->deleteAllFromGroup($group);
        static::assertCount(0, $this->mmRepo->findWithGroupInEmbeddedBroadcast($group)->toArray());
    }
}
