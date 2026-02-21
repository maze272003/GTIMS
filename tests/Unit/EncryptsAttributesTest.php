<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Traits\EncryptsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EncryptsAttributesTest extends TestCase
{
    public function test_is_encrypted_returns_false_for_plain_text(): void
    {
        $model = new EncryptableTestModel();
        $result = $this->invokeMethod($model, 'isEncrypted', ['plain text']);

        $this->assertFalse($result);
    }

    public function test_is_encrypted_returns_true_for_encrypted_text(): void
    {
        $encrypted = Crypt::encryptString('secret value');
        $model = new EncryptableTestModel();
        $result = $this->invokeMethod($model, 'isEncrypted', [$encrypted]);

        $this->assertTrue($result);
    }

    public function test_get_encryptable_attributes_returns_defined_attributes(): void
    {
        $model = new EncryptableTestModel();
        $attrs = $model->getEncryptableAttributes();

        $this->assertEquals(['secret_field'], $attrs);
    }

    public function test_empty_encryptable_returns_empty_array(): void
    {
        $model = new NonEncryptableTestModel();
        $attrs = $model->getEncryptableAttributes();

        $this->assertEquals([], $attrs);
    }

    /**
     * Helper to invoke protected/private methods for testing.
     */
    protected function invokeMethod(object $object, string $method, array $params = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $params);
    }
}

/**
 * Test model with encryptable attributes.
 */
class EncryptableTestModel extends Model
{
    use EncryptsAttributes;

    protected $encryptable = ['secret_field'];
}

/**
 * Test model without encryptable attributes.
 */
class NonEncryptableTestModel extends Model
{
    use EncryptsAttributes;
}
