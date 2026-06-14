<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OrderControllerTest extends WebTestCase
{
    public function testCreateOrderReturns201(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'customerEmail' => 'functional@test.com',
            'items' => ['guitar', 'amp'],
            'total' => 899.99,
        ]));

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame(16, strlen($data['id']), 'Order ID should be 16 hex characters');
        $this->assertSame('functional@test.com', $data['customerEmail']);
        $this->assertSame(['guitar', 'amp'], $data['items']);
        $this->assertSame(899.99, $data['total']);
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
            'total' => 29.99,
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
        $this->assertSame(29.99, $retrieved['total']);
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
            'total' => 10.0,
        ]));

        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'customerEmail' => 'second@test.com',
            'items' => ['b'],
            'total' => 20.0,
        ]));

        // List
        $client->request('GET', '/api/orders');

        $this->assertResponseStatusCodeSame(200);
        $orders = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($orders);
        $this->assertGreaterThanOrEqual(2, count($orders), 'Should contain at least the two created orders');

        // Each order should have required fields
        foreach ($orders as $order) {
            $this->assertArrayHasKey('id', $order);
            $this->assertArrayHasKey('customerEmail', $order);
            $this->assertArrayHasKey('items', $order);
            $this->assertArrayHasKey('total', $order);
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
            'total' => 9.99,
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
            'total' => 9.99,
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
            'total' => 9.99,
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
            'total' => 9.99,
        ]));

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('items', $data['errors']);
    }

    public function testCreateOrderWithZeroTotalReturns400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'customerEmail' => 'test@example.com',
            'items' => ['widget'],
            'total' => 0,
        ]));

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('total', $data['errors']);
    }

    public function testCreateOrderWithNegativeTotalReturns400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'customerEmail' => 'test@example.com',
            'items' => ['widget'],
            'total' => -5.00,
        ]));

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('total', $data['errors']);
    }

    public function testCreateOrderWithMultipleValidationErrorsReturns400(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'customerEmail' => '',
            'items' => [],
            'total' => 0,
        ]));

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('customerEmail', $data['errors']);
        $this->assertArrayHasKey('items', $data['errors']);
        $this->assertArrayHasKey('total', $data['errors']);
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

    public function testOrderStatusIsProcessedAfterSyncHandlers(): void
    {
        $client = static::createClient();

        // With sync:// transport in test env, handlers run synchronously.
        // They call markAsProcessed(), so the order should be 'processed' after creation.
        $client->request('POST', '/api/orders', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'customerEmail' => 'processed@test.com',
            'items' => ['widget'],
            'total' => 5.99,
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);

        // The response from POST might show 'pending' because the controller
        // returns the order BEFORE the message is dispatched,
        // but in sync mode the dispatch happens inline before the response...
        // Actually in sync mode, dispatch blocks until handlers complete.
        // So the status should be 'processed' by the time the response is sent.
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
