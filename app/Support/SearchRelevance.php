<?php

namespace App\Support;

class SearchRelevance
{
    /**
     * @var array<int, string>
     */
    private array $fragments = [];

    /**
     * @var array<int, mixed>
     */
    private array $bindings = [];

    public static function normalize(?string $value): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim((string) $value)) ?? '';

        return mb_strtolower($normalized);
    }

    /**
     * @return array<int, string>
     */
    public static function tokens(?string $value): array
    {
        $normalized = self::normalize($value);

        if ($normalized === '') {
            return [];
        }

        $tokens = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique($tokens));
    }

    public static function lower(string $expression): string
    {
        return "LOWER(COALESCE({$expression}, ''))";
    }

    public static function containsPattern(string $value): string
    {
        return '%'.self::escapeLike($value).'%';
    }

    public static function prefixPattern(string $value): string
    {
        return self::escapeLike($value).'%';
    }

    public function exact(string $expression, string $value, int $weight): self
    {
        if ($value === '' || $weight <= 0) {
            return $this;
        }

        return $this->addFragment("{$expression} = ?", [$value], $weight);
    }

    public function prefix(string $expression, string $value, int $weight): self
    {
        if ($value === '' || $weight <= 0) {
            return $this;
        }

        return $this->addFragment("{$expression} LIKE ? ESCAPE '!'", [self::prefixPattern($value)], $weight);
    }

    public function contains(string $expression, string $value, int $weight): self
    {
        if ($value === '' || $weight <= 0) {
            return $this;
        }

        return $this->addFragment("{$expression} LIKE ? ESCAPE '!'", [self::containsPattern($value)], $weight);
    }

    /**
     * @param array<int, scalar|\Stringable|null> $bindings
     */
    public function custom(string $predicate, array $bindings, int $weight): self
    {
        if ($weight <= 0) {
            return $this;
        }

        return $this->addFragment($predicate, $bindings, $weight);
    }

    /**
     * @param array<int, string> $tokens
     */
    public function tokenContains(string $expression, array $tokens, int $weight): self
    {
        foreach ($tokens as $token) {
            $this->contains($expression, $token, $weight);
        }

        return $this;
    }

    /**
     * @return array{sql: string, bindings: array<int, mixed>}
     */
    public function compile(string $alias = 'relevance_score'): array
    {
        if ($this->fragments === []) {
            return [
                'sql' => "0 as {$alias}",
                'bindings' => [],
            ];
        }

        return [
            'sql' => '('.implode(' + ', $this->fragments).") as {$alias}",
            'bindings' => $this->bindings,
        ];
    }

    /**
     * @param array<int, mixed> $bindings
     */
    private function addFragment(string $predicate, array $bindings, int $weight): self
    {
        $this->fragments[] = "CASE WHEN {$predicate} THEN {$weight} ELSE 0 END";
        array_push($this->bindings, ...$bindings);

        return $this;
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_!');
    }
}
