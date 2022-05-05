<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\AsyncTestPaymentHandler;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 * @group store-api
 */
class ChangeProfileRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var string
     */
    private $customerId;

    private string $salesChannelId;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);
        $this->customerRepository = $this->getContainer()->get('customer.repository');

        $email = Uuid::randomHex() . '@example.com';
        $this->customerId = $this->createCustomer('shopware', $email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'shopware',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $response['contextToken']);
    }

    public function testEmptyRequest(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);

        $sources = array_column(array_column($response['errors'], 'source'), 'pointer');
        static::assertContains('/firstName', $sources);
        static::assertContains('/lastName', $sources);
    }

    public function testChangeName(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                [
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertTrue($response['success']);

        $this->browser->request('GET', '/store-api/account/customer');
        $customer = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('Max', $customer['firstName']);
        static::assertSame('Mustermann', $customer['lastName']);
        static::assertSame($this->getValidSalutationId(), $customer['salutationId']);
    }

    public function testChangeProfileDataWithCommercialAccount(): void
    {
        $changeData = [
            'salutationId' => $this->getValidSalutationId(),
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'company' => 'Test Company',
            'vatIds' => [
                'DE123456789',
            ],
        ];
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                $changeData
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertTrue($response['success']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $this->customerId));
        $customer = $this->customerRepository->search($criteria, $this->ids->context)->first();

        static::assertEquals(['DE123456789'], $customer->getVatIds());
        static::assertEquals($changeData['company'], $customer->getCompany());
        static::assertEquals($changeData['firstName'], $customer->getFirstName());
        static::assertEquals($changeData['lastName'], $customer->getLastName());
    }

    public function testChangeProfileDataWithCommercialAccountAndVatIdsIsEmpty(): void
    {
        $this->setVatIdOfTheCountryToValidateFormat();

        $changeData = [
            'salutationId' => $this->getValidSalutationId(),
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'company' => 'Test Company',
            'vatIds' => [],
        ];
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                $changeData
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertTrue($response['success']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $this->customerId));
        $customer = $this->customerRepository->search($criteria, $this->ids->context)->first();

        static::assertNull($customer->getVatIds());
        static::assertEquals($changeData['company'], $customer->getCompany());
        static::assertEquals($changeData['firstName'], $customer->getFirstName());
        static::assertEquals($changeData['lastName'], $customer->getLastName());
    }

    public function dataProviderVatIds(): \Generator
    {
        yield 'Error when vatIds is require but no validate format, and has value is empty' => [
            [''],
            [
                'required' => true,
                'validateFormat' => false,
            ],
            false,
            null,
        ];

        yield 'Error when vatIds is require but no validate format, and without vatIds in parameters' => [
            null,
            [
                'required' => true,
                'validateFormat' => false,
            ],
            false,
            null,
        ];

        yield 'Error when vatIds is require but no validate format, and has value is null and empty value' => [
            [null, ''],
            [
                'required' => true,
                'validateFormat' => false,
            ],
            false,
            null,
        ];

        yield 'Success when vatIds is require but no validate format, and has one of the value is not null' => [
            [null, 'some-text'],
            [
                'required' => true,
                'validateFormat' => false,
            ],
            true,
            ['some-text'],
        ];

        yield 'Success when vatIds is require but no validate format, and has value is random string' => [
            ['some-text'],
            [
                'required' => true,
                'validateFormat' => false,
            ],
            true,
            ['some-text'],
        ];

        yield 'Success when vatIds need to validate format but no require and has value is empty' => [
            [],
            [
                'required' => false,
                'validateFormat' => true,
            ],
            true,
            null,
        ];

        yield 'Success when vatIds need to validate format but no require and has value is null' => [
            [null],
            [
                'required' => false,
                'validateFormat' => true,
            ],
            true,
            null,
        ];

        yield 'Success when vatIds need to validate format but no require and has value is blank' => [
            [''],
            [
                'required' => false,
                'validateFormat' => true,
            ],
            true,
            null,
        ];

        yield 'Error when vatIds need to validate format but no require and has value is boolean' => [
            [true],
            [
                'required' => false,
                'validateFormat' => true,
            ],
            false,
            null,
        ];

        yield 'Error when vatIds need to validate format but no require and has value is incorrect format' => [
            ['random-string'],
            [
                'required' => false,
                'validateFormat' => true,
            ],
            false,
            null,
        ];

        yield 'Success when vatIds need to validate format but no require and has value is correct format' => [
            ['123456789'],
            [
                'required' => false,
                'validateFormat' => true,
            ],
            true,
            ['123456789'],
        ];
    }

    /**
     * @dataProvider dataProviderVatIds
     */
    public function testChangeVatIdsOfCommercialAccount(?array $vatIds, array $constraint, bool $shouldBeValid, ?array $expectedVatIds): void
    {
        if (isset($constraint['required']) && $constraint['required']) {
            $this->setVatIdOfTheCountryToBeRequired();
        }

        if (isset($constraint['validateFormat']) && $constraint['validateFormat']) {
            $this->setVatIdOfTheCountryToValidateFormat();
        }

        $changeData = [
            'salutationId' => $this->getValidSalutationId(),
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'company' => 'Test Company',
        ];
        if ($vatIds !== null) {
            $changeData['vatIds'] = $vatIds;
        }

        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                $changeData
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        if (!$shouldBeValid) {
            static::assertArrayHasKey('errors', $response);

            $sources = array_column(array_column($response['errors'], 'source'), 'pointer');
            static::assertContains('/vatIds', $sources);

            return;
        }

        static::assertTrue($response['success']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $this->customerId));
        $customer = $this->customerRepository->search($criteria, $this->ids->context)->first();

        if ($expectedVatIds === null) {
            static::assertNull($customer->getVatIds());
        } else {
            static::assertEquals($expectedVatIds, $customer->getVatIds());
        }

        static::assertEquals($changeData['company'], $customer->getCompany());
        static::assertEquals($changeData['firstName'], $customer->getFirstName());
        static::assertEquals($changeData['lastName'], $customer->getLastName());
    }

    public function testChangeProfileDataWithPrivateAccount(): void
    {
        $changeData = [
            'salutationId' => $this->getValidSalutationId(),
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'firstName' => 'FirstName',
            'lastName' => 'LastName',
        ];
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                $changeData
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertTrue($response['success']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $this->customerId));
        $customer = $this->customerRepository->search($criteria, $this->ids->context)->first();

        static::assertNull($customer->getVatIds());
        static::assertEquals('', $customer->getCompany());
        static::assertEquals($changeData['firstName'], $customer->getFirstName());
        static::assertEquals($changeData['lastName'], $customer->getLastName());
    }

    private function createData(): void
    {
        $data = [
            [
                'id' => $this->ids->create('payment'),
                'name' => $this->ids->get('payment'),
                'active' => true,
                'handlerIdentifier' => AsyncTestPaymentHandler::class,
                'availabilityRule' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'asd',
                    'priority' => 2,
                ],
            ],
            [
                'id' => $this->ids->create('payment2'),
                'name' => $this->ids->get('payment2'),
                'active' => true,
                'handlerIdentifier' => AsyncTestPaymentHandler::class,
                'availabilityRule' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'asd',
                    'priority' => 2,
                ],
            ],
        ];

        $this->getContainer()->get('payment_method.repository')
            ->create($data, $this->ids->context);
    }

    private function createCustomer(?string $password = null, ?string $email = null, ?bool $guest = false): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        if ($email === null) {
            $email = Uuid::randomHex() . '@example.com';
        }

        if ($password === null) {
            $password = Uuid::randomHex();
        }

        $this->getContainer()->get('customer.repository')->create([
            [
                'id' => $customerId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId($this->ids->create('sales-channel')),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'active' => true,
                    'description' => 'Default payment method',
                    'handlerIdentifier' => SyncTestPaymentHandler::class,
                    'availabilityRule' => [
                        'id' => Uuid::randomHex(),
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'cartCartAmount',
                                'value' => [
                                    'operator' => '>=',
                                    'amount' => 0,
                                ],
                            ],
                        ],
                    ],
                    'salesChannels' => [
                        [
                            'id' => TestDefaults::SALES_CHANNEL,
                        ],
                    ],
                ],
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'guest' => $guest,
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ], Context::createDefaultContext());

        return $customerId;
    }

    private function setVatIdOfTheCountryToValidateFormat(): void
    {
        $this->getContainer()->get(Connection::class)
            ->executeUpdate('UPDATE `country` SET `check_vat_id_pattern` = 1, `vat_id_pattern` = "(DE)?[0-9]{9}"
                 WHERE id = :id', [
                'id' => Uuid::fromHexToBytes($this->getValidCountryId($this->ids->create('sales-channel'))),
            ]);
    }

    private function setVatIdOfTheCountryToBeRequired(): void
    {
        $this->getContainer()->get(Connection::class)
            ->executeUpdate('UPDATE `country` SET `vat_id_required` = 1
                 WHERE id = :id', [
                'id' => Uuid::fromHexToBytes($this->getValidCountryId($this->ids->create('sales-channel'))),
            ]);
    }
}
