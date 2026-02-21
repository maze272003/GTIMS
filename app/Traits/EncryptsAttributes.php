<?php

namespace App\Traits;

use Illuminate\Support\Facades\Crypt;

/**
 * Trait for encrypting/decrypting sensitive model attributes.
 *
 * Usage: Define a $encryptable property on the model with an array of attribute names.
 *
 * @property array $encryptable
 */
trait EncryptsAttributes
{
    /**
     * Boot the trait and register model event listeners for automatic encryption.
     */
    public static function bootEncryptsAttributes(): void
    {
        static::saving(function ($model) {
            $model->encryptAttributes();
        });
    }

    /**
     * Get the list of attributes that should be encrypted.
     */
    public function getEncryptableAttributes(): array
    {
        return property_exists($this, 'encryptable') ? $this->encryptable : [];
    }

    /**
     * Encrypt all encryptable attributes before saving.
     */
    public function encryptAttributes(): void
    {
        foreach ($this->getEncryptableAttributes() as $attribute) {
            $value = $this->attributes[$attribute] ?? null;
            if ($value !== null && !$this->isEncrypted($value)) {
                $this->attributes[$attribute] = Crypt::encryptString($value);
            }
        }
    }

    /**
     * Decrypt an attribute when accessing it.
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if (in_array($key, $this->getEncryptableAttributes()) && $value !== null) {
            try {
                return Crypt::decryptString($value);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                // Value is not encrypted (e.g., legacy data), return as-is
                return $value;
            }
        }

        return $value;
    }

    /**
     * Check if a value appears to be already encrypted.
     */
    protected function isEncrypted(string $value): bool
    {
        // Laravel encrypted values are base64-encoded JSON with iv, value, mac keys
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }

        $json = json_decode($decoded, true);

        return is_array($json) && isset($json['iv'], $json['value'], $json['mac']);
    }
}
