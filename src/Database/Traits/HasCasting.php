<?php

namespace Maharlika\Database\Traits;

use Maharlika\Facades\Hash;
use Maharlika\Support\Carbon;

trait HasCasting
{
    const CREATED_AT = 'created_at';

    const UPDATED_AT = 'updated_at';


    /**
     * Check if an attribute has a cast type defined
     * 
     * @param string $key
     * @param array|string|null $types Optional cast types to check against
     * @return bool
     */
    protected function hasCast(string $key, array|string|null $types = null): bool
    {
        $casts = $this->getCasts();


        if (!array_key_exists($key, $casts)) {
            return false;
        }

        // If no types specified, just check if cast exists
        if (is_null($types)) {
            return true;
        }

        // Convert types to array
        $types = (array) $types;

        // Get the cast type for this key
        $castType = $this->getCastType($key);

        $result = in_array($castType, $types);

        return $result;
    }

    /**
     * Cast an attribute to its defined type (for reading from database)
     */
    protected function castAttribute(string $key, mixed $value): mixed
    {
        $casts = $this->getCasts();

        if (!array_key_exists($key, $casts)) {
            return $value;
        }

        // Don't cast null values (except for specific types)
        if (is_null($value) && !in_array($casts[$key], ['array', 'json', 'collection'])) {
            return $value;
        }

        $castType = $casts[$key];

        return match ($castType) {
            'int', 'integer' => (int) $value,
            'real', 'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => $this->castToBoolean($value),
            'array', 'json' => $this->castToArray($value),
            'object' => $this->castToObject($value),
            'collection' => $this->castToCollection($value),
            'date' => $this->asDate($value),
            'datetime' => $this->asDateTime($value),
            'timestamp' => $this->asTimestamp($value),
            'hashed', 'password' => $this->castToHashed($value),
            default => $value,
        };
    }

    /**
     * Cast an attribute for saving to database
     */
    protected function castAttributeForDatabase(string $key, mixed $value): mixed
    {
        $casts = $this->getCasts();

        if (!array_key_exists($key, $casts)) {
            return $value;
        }

        $castType = $casts[$key];

        return match ($castType) {
            'array', 'json' => $this->castToJson($value),
            'hashed', 'password' => $this->castToHashed($value),
            'date' => $this->castDateForDatabase($value),
            'datetime' => $this->castDateTimeForDatabase($value),
            'bool', 'boolean' => $this->castBoolForDatabase($value),
            default => $value,
        };
    }

    /**
     * Cast value to JSON string for database storage
     */
    protected function castToJson(mixed $value): string
    {
        if (is_null($value)) {
            return json_encode([]);
        }

        // If already a valid JSON string, return as-is
        if (is_string($value)) {
            json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $value;
            }
        }

        // For everything else (arrays, objects, invalid JSON strings), encode
        return json_encode($value);
    }

    /**
     * Cast boolean for database (convert to 0 or 1)
     */
    protected function castBoolForDatabase(mixed $value): int
    {
        return $value ? 1 : 0;
    }

    /**
     * Cast date for database
     */
    protected function castDateForDatabase(mixed $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d');
        }

        if (is_string($value)) {
            return Carbon::parse($value)->format('Y-m-d');
        }

        return null;
    }

    /**
     * Cast datetime for database
     */
    protected function castDateTimeForDatabase(mixed $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d H:i:s');
        }

        if (is_string($value)) {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        }

        return null;
    }


    /**
     * Determine if the given attribute should be cast when setting
     */
    protected function shouldCastOnSet(string $key): bool
    {
        if (!$this->hasCast($key)) {
            return false;
        }

        $casts = $this->getCasts();

        // Always cast these types when setting
        $alwaysCastOnSet = ['hashed', 'password', 'array', 'json', 'collection'];

        return in_array($casts[$key], $alwaysCastOnSet);
    }


    /**
     * Cast value to boolean (handles various truthy/falsy values)
     */
    protected function castToBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        // Handle string representations
        if (is_string($value)) {
            $value = strtolower($value);
            return in_array($value, ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    /**
     * Cast value to array (for reading from database)
     */
    protected function castToArray(mixed $value): array
    {
        if (is_null($value)) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            // Handle empty strings
            if (trim($value) === '') {
                return [];
            }

            $decoded = json_decode($value, true);

            // Check for JSON decode errors
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }

            return is_array($decoded) ? $decoded : [];
        }

        return (array) $value;
    }

    /**
     * Cast value to object
     */
    protected function castToObject(mixed $value): object
    {
        if (is_object($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value);
            return is_object($decoded) ? $decoded : (object) [];
        }

        return (object) $value;
    }

    /**
     * Cast value to collection (array)
     */
    protected function castToCollection(mixed $value): array
    {
        return $this->castToArray($value);
    }

    /**
     * Cast value to hashed password using Hash facade
     * Only hashes if the value is not already hashed
     */
    protected function castToHashed(mixed $value): string
    {
        if (empty($value)) {
            return $value;
        }

        // Check if already hashed using Hash facade
        if (is_string($value) && Hash::isHashed($value)) {
            return $value;
        }

        // Hash the password using Hash facade (respects config)
        return Hash::make($value);
    }

    /**
     * Check if a value is already a bcrypt hash
     * 
     * @deprecated Use Hash::isHashed() instead
     */
    protected function isAlreadyHashed(string $value): bool
    {
        return Hash::isHashed($value);
    }

    /**
     * Cast value to date string (Y-m-d)
     */
    protected function asDate(mixed $value): string
    {
        if (is_null($value)) {
            return '';
        }

        $format = 'Y-m-d';

        if ($value instanceof Carbon) {
            return $value->format($format);
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format($format);
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value)->format($format);
        }

        if (is_string($value)) {
            return Carbon::parse($value)->format($format);
        }

        return '';
    }

    /**
     * Cast value to datetime string
     */
    protected function asDateTime(mixed $value): Carbon
    {
        $timezone = config('app.timezone', 'UTC');
        if ($value instanceof Carbon) {
            return $value->setTimezone($timezone);
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->setTimezone($timezone);
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value, $timezone);
        }

        if (is_string($value)) {
            return Carbon::parse($value, $timezone);
        }

        return Carbon::now($timezone); // fallback
    }


    /**
     * Cast value to timestamp
     */
    protected function asTimestamp(mixed $value): ?int
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->getTimestamp();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->getTimestamp();
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            return Carbon::parse($value)->getTimestamp();
        }

        return null;
    }

    /**
     * Get the casts array with automatic timestamp casting
     */
    public function getCasts(): array
    {
        $casts = $this->casts ?? [];

        // Automatically cast timestamp columns to datetime if timestamps are enabled
        if ($this->timestamps) {
            if (!isset($casts[static::CREATED_AT])) {
                $casts[static::CREATED_AT] = 'datetime';
            }
            if (!isset($casts[static::UPDATED_AT])) {
                $casts[static::UPDATED_AT] = 'datetime';
            }
        }

        // Also cast any columns in $dates array
        foreach ($this->dates ?? [] as $date) {
            if (!isset($casts[$date])) {
                $casts[$date] = 'datetime';
            }
        }

        return $casts;
    }

    /**
     * Get the type of cast for a model attribute.
     *
     * @param string $key
     * @return string
     */
    protected function getCastType(string $key): string
    {
        $casts = $this->getCasts();
        $castType = $casts[$key] ?? '';

        // Handle casts like 'decimal:2' -> 'decimal'
        if (str_contains($castType, ':')) {
            $result = explode(':', $castType, 2)[0];
            return $result;
        }

        return $castType;
    }

    /**
     * Determine if the given attribute should be cast when getting
     */
    protected function shouldCastOnGet(string $key): bool
    {
        if (!$this->hasCast($key)) {
            return false;
        }

        $casts = $this->getCasts();

        // Don't cast hashed/password when getting (they're already hashed)
        $skipOnGet = ['hashed', 'password'];

        return !in_array($casts[$key], $skipOnGet);
    }


    /**
     * Cast attributes array for database storage
     * 
     * @param array $attributes
     * @return array
     */
    protected function castAttributesForDatabase(array $attributes): array
    {
        $casted = [];

        foreach ($attributes as $key => $value) {
            $casted[$key] = $this->castAttributeForDatabase($key, $value);
        }

        return $casted;
    }
}
