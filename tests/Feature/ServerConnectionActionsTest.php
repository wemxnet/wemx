<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Extension;
use App\Models\Package;
use App\Models\ServerConnection;
use Extensions\Servers\Universal\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServerConnectionActionsTest extends TestCase
{
    use RefreshDatabase;

    protected ServerConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        Extension::query()->updateOrCreate(
            ['identifier' => 'server-universal'],
            [
                'namespace' => Server::class,
                'type' => 'server',
                'name' => 'Universal',
                'status' => 'enabled',
                'version' => '1.0.0',
            ]
        );

        $this->connection = ServerConnection::query()->create([
            'alias' => 'test-connection-'.uniqid(),
            'extension_identifier' => 'server-universal',
            'status' => 'healthy',
            'is_active' => true,
            'prevent_purchasing' => false,
            'receive_alerts' => false,
            'config' => [],
        ]);
    }

    public function test_admin_can_delete_server_connection_without_packages(): void
    {
        ServerConnection::actions()->deleteServerConnectionAsAdmin([
            'connection_id' => $this->connection->id,
        ]);

        $this->assertDatabaseMissing('server_connections', [
            'id' => $this->connection->id,
        ]);
    }

    public function test_admin_cannot_delete_server_connection_used_by_packages(): void
    {
        $category = Category::query()->create([
            'name' => 'Hosting',
            'slug' => 'hosting-'.uniqid(),
            'icon' => 'server',
            'status' => 'active',
        ]);

        $package = Package::query()->create([
            'category_id' => $category->id,
            'connection_id' => $this->connection->id,
            'slug' => 'starter-'.uniqid(),
            'name' => 'Starter '.uniqid(),
            'status' => 'active',
        ]);

        try {
            ServerConnection::actions()->deleteServerConnectionAsAdmin([
                'connection_id' => $this->connection->id,
            ]);

            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('connection_id', $exception->errors());
            $this->assertStringContainsString('#'.$package->id, $exception->errors()['connection_id'][0]);
        }

        $this->assertDatabaseHas('server_connections', [
            'id' => $this->connection->id,
        ]);
    }
}
