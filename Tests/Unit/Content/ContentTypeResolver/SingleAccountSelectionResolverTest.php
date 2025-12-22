<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\HeadlessBundle\Tests\Unit\Content\ContentTypeResolver;

use JMS\Serializer\SerializationContext;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\ContactBundle\Api\Account;
use Sulu\Bundle\ContactBundle\Contact\AccountManager;
use Sulu\Bundle\ContactBundle\Entity\AccountInterface;
use Sulu\Bundle\HeadlessBundle\Content\ContentTypeResolver\SingleAccountSelectionResolver;
use Sulu\Bundle\HeadlessBundle\Content\ContentView;
use Sulu\Bundle\HeadlessBundle\Content\Serializer\AccountSerializerInterface;

class SingleAccountSelectionResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var AccountManager|ObjectProphecy
     */
    private $accountManager;

    /**
     * @var AccountSerializerInterface|ObjectProphecy
     */
    private $accountSerializer;

    /**
     * @var SingleAccountSelectionResolver
     */
    private $singleAccountSelectionResolver;

    private FieldMetadata $fieldMetadata;

    protected function setUp(): void
    {
        $this->accountManager = $this->prophesize(AccountManager::class);
        $this->accountSerializer = $this->prophesize(AccountSerializerInterface::class);
        $this->fieldMetadata = new FieldMetadata('account');

        $this->singleAccountSelectionResolver = new SingleAccountSelectionResolver(
            $this->accountManager->reveal(),
            $this->accountSerializer->reveal()
        );
    }

    public function testGetContentType(): void
    {
        self::assertSame('single_account_selection', $this->singleAccountSelectionResolver::getContentType());
    }

    public function testResolve(): void
    {
        $locale = 'en';

        $account = $this->prophesize(AccountInterface::class);
        $apiAccount = $this->prophesize(Account::class);
        $apiAccount->getEntity()->willReturn($account->reveal());

        $data = 3;

        $this->accountManager->getById(3, $locale)->willReturn($apiAccount->reveal());
        $this->accountSerializer->serialize($account, $locale, Argument::type(SerializationContext::class))->willReturn([
            'id' => 3,
            'depth' => 1,
            'name' => 'Sulu GmbH',
            'corporation' => 'Digital Agency',
            'logo' => [
                'id' => 2,
                'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
            ],
        ]);

        $result = $this->singleAccountSelectionResolver->resolve($data, $this->fieldMetadata, $locale);

        $this->assertInstanceOf(ContentView::class, $result);
        $this->assertSame(
            [
                'id' => 3,
                'depth' => 1,
                'name' => 'Sulu GmbH',
                'corporation' => 'Digital Agency',
                'logo' => [
                    'id' => 2,
                    'formatUri' => '/media/2/{format}/media-2.jpg?v=1-0',
                ],
            ],
            $result->getContent()
        );

        $this->assertSame(
            ['id' => 3],
            $result->getView()
        );
    }

    public function testResolveDataIsNull(): void
    {
        $locale = 'en';

        $result = $this->singleAccountSelectionResolver->resolve(null, $this->fieldMetadata, $locale);

        $this->assertNull($result->getContent());

        $this->assertSame(['id' => null], $result->getView());
    }
}
