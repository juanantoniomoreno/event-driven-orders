<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OrderControllerTest extends WebTestCase
{
    public function testCreateOrderReturns201WithNestedMoney(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'customerEmail' => 'functional@test.com',
            'items' => ['guitar', 'amp'],
            'total' => ['amount' => 89999, 'currency' => 'USD'],
        ]));

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame(16, strlen($data['id']), 'Order ID should be 16 hex characters');
        $this->assertSame('functional@test.com', $data['customerEmail']);
        $this->assertSame(['guitar', 'amp'], $data['items']);
        $this->assertSame(['amount' => 89999, 'currency' => 'USD'], $data['total']);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('createdAt', $data);
    }

    public function testCreateOrderPersistsAndCanBeRetrieved(): void
    {
        $client = static::createClient();

        // Create
        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'customerEmail' => 'retrieve@test.com',
            'items' => ['book'],
            'total' => ['amount' => 2999, 'currency' => 'USD'],
        ]));

        $created = json_decode($client->getResponse()->getContent(), true);
        $orderId = $created['id'];

        // Retrieve
        $client->request('GET', "/api/orders/{$orderId}");

        $this->assertResponseStatusCodeSame(200);
        $retrieved = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame($orderId, $retrieved['id']);
        $this->assertSame('retrieve@test.com', $retrieved['customerEmail']);
        $this->assertSame(['book'], $retrieved['items']);
        $this->assertSame(['amount' => 2999, 'currency' => 'USD'], $retrieved['total']);
    }

    public function testListOrdersReturnsAllOrders(): void
    {
        $client = static::createClient();

        // Create two orders
        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'customerEmail' => 'first@test.com',
            'items' => ['a'],
            'total' => ['amount' => 1000, 'currency' => 'USD'],
        ]));

        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'customerEmail' => 'second@test.com',
            'items' => ['b'],
            'total' => ['amount' => 2000, 'currency' => 'USD'],
        ]));

        // List
        $client->request('GET', '/api/orders');

        $this->assertResponseStatusCodeSame(200);
        $orders = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($orders);
        $this->assertGreaterThanOrEqual(2, count($orders), 'Should contain at least the two created orders');

        // Each order should have required fields including nested total
        foreach ($orders as $order) {
            $this->assertArrayHasKey('id', $order);
            $this->assertArrayHasKey('customerEmail', $order);
            $this->assertArrayHasKey('items', $order);
            $this->assertArrayHasKey('total', $order);
            $this->assertArrayHasKey('amount', $order['total']);
            $this->assertArrayHasKey('currency', $order['total']);
            $this->assertArrayHasKey('status', $order);
            $this->assertArrayHasKey('createdAt', $order);
        }
    }

    public function testGetNonExistentOrderReturns404(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/orders/nonexistent-order-id');

        $this->assertResponseStatusCodeSame(404);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
        $this->assertSame('Order not found', $data['error']);
    }

    public function testCreateOrderWithEmptyBodyReturns400(): void
    {
        $client = static::createClient();

        // An empty JSON object has no required fields — validation must reject it
        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: '{}');

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('customerEmail', $data['errors']);
        $this->assertArrayHasKey('items', $data['errors']);
        $this->assertArrayHasKey('total', $data['errors']);
    }

    public function testCreateOrderWithEmptyEmailReturns400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'customerEmail' => '',
            'items' => ['widget'],
            'total' => ['amount' => 999, 'currency' => 'USD'],
        ]));

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('customerEmail', $data['errors']);
    }

    public function testCreateOrderWithInvalidEmailReturns400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'customerEmail' => 'not-an-email',
            'items' => ['widget'],
            'total' => ['amount' => 999, 'currency' => 'USD'],
        ]));

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('customerEmail', $data['errors']);
    }

    public function testCreateOrderWithEmptyItemsReturns400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'customerEmail' => 'test@example.com',
            'items' => [],
            'total' => ['amount' => 999, 'currency' => 'USD'],
        ]));

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('items', $data['errors']);
    }

    public function testCreateOrderWithMissingItemsReturns400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'customerEmail' => 'test@example.com',
            'total' => ['amount' => 999, 'currency' => 'USD'],
        ]));

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('items', $data['errors']);
    }

    public function testCreateOrderWithZeroTotalAmountReturns400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'customerEmail' => 'test@example.com',
            'items' => ['widget'],
            'total' => ['amount' => 0, 'currency' => 'USD'],
        ]));

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('total.amount', $data['errors']);
    }

    public function testCreateOrderWithNegativeTotalAmountReturns400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'customerEmail' => 'test@example.com',
            'items' => ['widget'],
            'total' => ['amount' => -500, 'currency' => 'USD'],
        ]));

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('total.amount', $data['errors']);
    }

    public function testCreateOrderWithUnsupportedCurrencyReturns400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'customerEmail' => 'test@example.com',
            'items' => ['widget'],
            'total' => ['amount' => 999, 'currency' => 'EUR'],
        ]));

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('total.currency', $data['errors']);
    }

    public function testCreateOrderWithMultipleValidationErrorsReturns400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'customerEmail' => '',
            'items' => [],
            'total' => ['amount' => 0, 'currency' => 'USD'],
        ]));

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('customerEmail', $data['errors']);
        $this->assertArrayHasKey('items', $data['errors']);
        $this->assertArrayHasKey('total.amount', $data['errors']);
    }

    public function testCreateOrderWithMalformedJsonReturns400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: 'not json');

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
    }

    public function testCreateOrderWithLegacyFlatFloatTotalReturns400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'customerEmail' => 'legacy@test.com',
            'items' => ['widget'],
            'total' => 899.99,
        ]));

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        // The total field should have a validation error since float is not a valid MoneyInput
        $this->assertArrayHasKey('total', $data['errors']);
    }

    public function testOrderStatusIsProcessedAfterSyncHandlers(): void
    {
        $client = static::createClient();

        // With sync:// transport in test env, handlers run synchronously.
        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'customerEmail' => 'processed@test.com',
            'items' => ['widget'],
            'total' => ['amount' => 599, 'currency' => 'USD'],
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame(
            'processed',
            $data['status'],
            'In sync transport mode, handlers run before the response, so status should be processed'
        );

        // Double-check by fetching the order fresh from DB
        $client->request('GET', "/api/orders/{$data['id']}");
        $retrieved = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('processed', $retrieved['status'], 'Order should be processed when fetched from DB');
    }
}