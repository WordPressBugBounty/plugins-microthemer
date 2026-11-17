<?php

declare(strict_types=1);

namespace Microthemer\Request;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Schema — a small, declarative, Zod-ish sanitizer for structured WordPress request data
 * ($_POST/$_GET arrays, decoded JSON bodies).
 *
 * Rejected a Composer library (CuyZ/Valinor, respect/validation, opis/json-schema): for a wp.org plugin
 * they need dependency scoping (Strauss/PHP-Scoper), bloat the review surface, and hide the explicit
 * sanitize_*()/esc_*() calls reviewers/phpcs scan for. This class calls the real WP sanitizers directly.
 *
 * A schema is a plain array of field definitions keyed by field name, e.g.:
 *   [
 *       'title' => ['type' => 'text', 'required' => true, 'maxLength' => 200],
 *       'mode'  => ['type' => 'key', 'validator' => ['enum' => ['auto', 'manual']], 'default' => 'auto'],
 *       'tags'  => ['type' => 'array', 'items' => ['type' => 'text']],
 *       'props' => ['type' => 'array', 'wildcard' => ['type' => 'text'], 'keySanitizer' => 'sanitize_key'],
 *   ]
 *
 * Field definition keys:
 *   - type         one of: key, text, textarea, email, url, int, float, bool, raw, mixed, array.
 *                  'mixed' = scalar OR opaque array (kept losslessly, but still depth/size bounded).
 *   - required     missing/null input is an error instead of falling back to 'default'.
 *   - default      used verbatim (not re-sanitized) when the field is absent and not required.
 *   - sanitizer    callable overriding the built-in WP sanitizer for scalar types.
 *   - arraySanitizer (type=mixed) callable applied to an array value as a whole.
 *   - maxLength    truncates sanitized strings longer than this many CHARACTERS (not bytes).
 *   - validator    callable(mixed):bool|string, or ['enum'=>[...]] | ['regex'=>'/../'] | ['range'=>['min'=>,'max'=>]].
 *   - schema       (type=array) named sub-fields, same shape as a schema.
 *   - items        (type=array) a field def every element of a sequential list must match.
 *   - wildcard     (type=array) a field def applied to any key not covered by 'schema'.
 *   - keySanitizer callable applied to wildcard key names (default sanitize_key).
 *   - keyMatch     (type=array) regex or callable(string):bool limiting WHICH keys the wildcard claims.
 *                  Omit for catch-all. Set it to combine 'wildcard' with 'passthrough' on one node:
 *                  matching keys get sanitized, the rest pass through untouched.
 *   - passthrough  (type=array) keys not claimed by 'schema'/'wildcard' are carried through unchanged
 *                  instead of dropped — deliberately dumb, no sanitization/interpretation, for opaque
 *                  settings blobs (e.g. pro-only preference keys) that must round-trip. See T-wpm3ab.
 *   - maxItems     caps how many elements of 'items'/'wildcard'/'passthrough' are kept.
 *
 * Unknown keys are dropped by default (strict mode) at every array level unless 'wildcard' or
 * 'passthrough' is set for that level.
 *
 * Input must already be unslashed — pass wp_unslash($_POST), not $_POST. See sanitize().
 */
class Schema
{
    private const DEFAULT_MAX_DEPTH = 20;
    private const DEFAULT_MAX_ITEMS = 500;
    private const DEFAULT_MAX_STRING_LENGTH = 65535;
    private const DEFAULT_MAX_NODES = 5000;
    private const DEFAULT_MAX_JSON_BYTES = 1048576; // 1 MB
    private const DEFAULT_JSON_DEPTH = 64;

    /** @var array<string,array<string,mixed>> */
    private array $schema;

    private int $maxDepth;
    private int $maxNodes;
    private int $defaultMaxItems;
    private int $defaultMaxStringLength;
    private int $maxJsonBytes;

    /** running total of array nodes visited during the current sanitize() call */
    private int $nodeCount = 0;

    /**
     * @param array<string,array<string,mixed>> $schema field name => field definition
     * @param array<string,mixed> $options maxDepth, maxNodes, maxItems, maxStringLength, maxJsonBytes
     */
    public function __construct(array $schema, array $options = [])
    {
        $this->schema = $schema;
        $this->maxDepth = (int) ($options['maxDepth'] ?? self::DEFAULT_MAX_DEPTH);
        $this->maxNodes = (int) ($options['maxNodes'] ?? self::DEFAULT_MAX_NODES);
        $this->defaultMaxItems = (int) ($options['maxItems'] ?? self::DEFAULT_MAX_ITEMS);
        $this->defaultMaxStringLength = (int) ($options['maxStringLength'] ?? self::DEFAULT_MAX_STRING_LENGTH);
        $this->maxJsonBytes = (int) ($options['maxJsonBytes'] ?? self::DEFAULT_MAX_JSON_BYTES);
    }

    /**
     * Sanitize an already-decoded, already-unslashed request array against the schema.
     *
     * Unslashing is deliberately the CALLER's job: pass `wp_unslash($_POST)`, not `$_POST`. Doing it here
     * would double-unslash anything arriving via sanitizeJson() (which must unslash the raw string before
     * json_decode), silently eating backslashes in CSS escapes (\f101), regexes and Windows paths — which
     * would break the lossless round-trip guarantee the passthrough mode exists to provide. Keeping the
     * wp_unslash() call at the endpoint also keeps it visible to reviewers and phpcs, which is the point.
     *
     * @return array{value: array<string,mixed>, errors: list<array{path:string,code:string,message:string}>}
     */
    public function sanitize($input): array
    {
        $this->nodeCount = 0;

        if (!is_array($input)) {
            return [
                'value' => [],
                'errors' => [$this->error('', 'invalid_type', 'Expected an array at the root.')],
            ];
        }

        $rootDef = ['type' => 'array', 'schema' => $this->schema];

        [$value, $errors] = $this->sanitizeArrayNode($rootDef, $input, '', 0);

        return ['value' => $value ?? [], 'errors' => $errors];
    }

    /**
     * json_decode() a raw (still-slashed) JSON request field, then sanitize it against the schema —
     * "sanitize this JSON POST field" in one call. Pass the raw $_POST value here: unslashing happens
     * once, on the string, before decoding (sanitize() does not unslash — see its docblock).
     *
     * @return array{value: array<string,mixed>, errors: list<array{path:string,code:string,message:string}>}
     */
    public function sanitizeJson(string $rawJson, ?int $maxBytes = null): array
    {
        $limit = $maxBytes ?? $this->maxJsonBytes;

        if (strlen($rawJson) > $limit) {
            return [
                'value' => [],
                'errors' => [$this->error('', 'payload_too_large', 'JSON payload exceeds the allowed size.')],
            ];
        }

        $unslashed = function_exists('wp_unslash') ? wp_unslash($rawJson) : stripslashes($rawJson);

        $decoded = json_decode($unslashed, true, self::DEFAULT_JSON_DEPTH);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'value' => [],
                'errors' => [$this->error('', 'invalid_json', 'Payload is not valid JSON: ' . json_last_error_msg())],
            ];
        }

        if (!is_array($decoded)) {
            return [
                'value' => [],
                'errors' => [$this->error('', 'invalid_type', 'Expected a JSON object/array at the root.')],
            ];
        }

        return $this->sanitize($decoded);
    }

    /**
     * Returns null for $value only when the node is unusable outright (wrong shape / recursion guard
     * tripped); a soft, best-effort truncation (e.g. 'too_many_items') still returns a real array so
     * callers keep the partial result instead of discarding an otherwise-valid field.
     *
     * @return array{0: ?array<string,mixed>, 1: list<array{path:string,code:string,message:string}>}
     */
    private function sanitizeArrayNode(array $def, $rawValue, string $path, int $depth): array
    {
        if (!is_array($rawValue)) {
            return [null, [$this->error($path, 'invalid_type', 'Expected an array/object but got a scalar.')]];
        }

        if ($depth > $this->maxDepth) {
            return [null, [$this->error($path, 'max_depth_exceeded', 'Payload nesting is too deep.')]];
        }

        $this->nodeCount++;
        if ($this->nodeCount > $this->maxNodes) {
            return [null, [$this->error($path, 'payload_too_large', 'Payload has too many fields to process.')]];
        }

        $errors = [];
        $remaining = $rawValue;

        // Output key order follows INPUT order, not schema-declaration order. Microthemer's saved
        // settings are a serialized WordPress option whose array order is meaningful —
        // UnavailableSettings::merge_groups() preserves stored order deliberately, and for CSS the
        // order of declarations can change what actually renders. Emitting named fields in schema
        // order would silently rewrite both on every save. Keys absent from the input (i.e. applied
        // defaults) sort last, keeping their schema order among themselves via PHP 8's stable sort.
        $positions = array_flip(array_keys($rawValue));
        $order = [];

        // 1. Named fields.
        $result = [];
        $namedSchema = $def['schema'] ?? null;
        if (is_array($namedSchema)) {
            foreach ($namedSchema as $key => $fieldDef) {
                $childPath = $path === '' ? (string) $key : $path . '.' . $key;
                $present = array_key_exists($key, $remaining) && $remaining[$key] !== null;
                $fieldRaw = $remaining[$key] ?? null;
                $order[$key] = $positions[$key] ?? PHP_INT_MAX;
                unset($remaining[$key]);

                if (!$present) {
                    if (!empty($fieldDef['required'])) {
                        $errors[] = $this->error($childPath, 'required', 'Field is required.');
                        continue;
                    }
                    if (array_key_exists('default', $fieldDef)) {
                        $result[$key] = $fieldDef['default'];
                    }
                    continue;
                }

                [$sanitized, $fieldErrors] = $this->sanitizeField($fieldDef, $fieldRaw, $childPath, $depth + 1);
                $errors = array_merge($errors, $fieldErrors);

                // A scalar failure or an unusable nested array yields null: fall back to 'default'.
                // A nested array with only soft warnings (e.g. truncation) still yields a real,
                // best-effort array here, so it is kept rather than discarded.
                if ($sanitized === null && $fieldErrors !== []) {
                    if (array_key_exists('default', $fieldDef)) {
                        $result[$key] = $fieldDef['default'];
                    }
                    continue;
                }

                $result[$key] = $sanitized;
            }
        }

        // 2. Sequential list items (type=array using 'items' instead of / alongside 'schema').
        if (isset($def['items']) && is_array($def['items'])) {
            $itemDef = $def['items'];
            $limit = (int) ($def['maxItems'] ?? $this->defaultMaxItems);
            $list = array_values($remaining);
            $remaining = [];

            if (count($list) > $limit) {
                $errors[] = $this->error($path, 'too_many_items', 'List has too many items; extra items were dropped.');
                $list = array_slice($list, 0, $limit);
            }

            foreach ($list as $index => $itemValue) {
                $childPath = $path . '[' . $index . ']';
                [$sanitized, $itemErrors] = $this->sanitizeField($itemDef, $itemValue, $childPath, $depth + 1);
                $errors = array_merge($errors, $itemErrors);
                // Keep anything that produced a usable value. Only a hard failure (null + errors) is
                // dropped; a nested array carrying nothing worse than a truncation warning is still real
                // data, and must survive here exactly as it does in the named-field branch above.
                if ($sanitized !== null || $itemErrors === []) {
                    $result[] = $sanitized;
                }
            }

            return [$result, $errors];
        }

        // 3. Wildcard map — dynamic keys not consumed by the named schema.
        //
        // With no 'keyMatch' the wildcard is a catch-all and claims everything left. Set 'keyMatch' to a
        // regex or callable to claim only some keys; the rest fall through to the passthrough block below
        // (or are dropped in strict mode). That combination is what lets one node sanitize the properties
        // it understands while still round-tripping opaque pro-only keys it must not touch.
        if (isset($def['wildcard']) && is_array($def['wildcard'])) {
            $wildcardDef = $def['wildcard'];
            $keySanitizer = $def['keySanitizer'] ?? 'sanitize_key';
            $keyMatch = $def['keyMatch'] ?? null;
            $limit = (int) ($def['maxItems'] ?? $this->defaultMaxItems);
            $processed = 0;
            $overflowed = false;

            foreach ($remaining as $key => $value) {
                if ($keyMatch !== null && !$this->keyMatches($keyMatch, (string) $key)) {
                    continue; // not ours: leave in $remaining for passthrough / strict drop
                }

                // Claimed by the wildcard either way, so overflow is dropped here rather than leaking
                // into the passthrough block below unsanitized.
                unset($remaining[$key]);

                // Count every claimed key, not just the ones that validate: counting successes only let a
                // payload of entirely-invalid keys iterate without bound and accumulate one error record
                // each, which is the cheap way to exhaust memory on this endpoint.
                if ($processed >= $limit) {
                    $overflowed = true;
                    continue;
                }
                $processed++;

                $safeKey = is_callable($keySanitizer) ? $keySanitizer((string) $key) : sanitize_key((string) $key);
                if ($safeKey === '' || $safeKey === null) {
                    continue;
                }

                // Key sanitizers are lossy — sanitize_key() lowercases and strips, so 'Color', 'color'
                // and 'COLOR!' all collapse to 'color'. Letting the last one win would silently discard a
                // user's earlier setting, and would let a crafted later key shadow a value already
                // written by the named schema. First write wins; every collision is reported.
                if (array_key_exists($safeKey, $result)) {
                    $errors[] = $this->error(
                        $path === '' ? (string) $safeKey : $path . '.' . $safeKey,
                        'duplicate_key',
                        sprintf('Key "%s" collides with an existing key after sanitization; kept the first.', $key)
                    );
                    continue;
                }

                $childPath = $path === '' ? (string) $safeKey : $path . '.' . $safeKey;
                [$sanitized, $fieldErrors] = $this->sanitizeField($wildcardDef, $value, $childPath, $depth + 1);
                $errors = array_merge($errors, $fieldErrors);

                if ($sanitized !== null || $fieldErrors === []) {
                    $order[$safeKey] = $positions[$key] ?? PHP_INT_MAX;
                    $result[$safeKey] = $sanitized;
                }
            }

            if ($overflowed) {
                $errors[] = $this->error($path, 'too_many_items', 'Map has too many entries; extras were dropped.');
            }
        }

        // 4. Passthrough — carry remaining keys through unmodified (opaque, forward-compatible data).
        if (!empty($def['passthrough'])) {
            $limit = (int) ($def['maxItems'] ?? $this->defaultMaxItems);
            $count = 0;

            foreach ($remaining as $key => $value) {
                if ($count >= $limit) {
                    $errors[] = $this->error($path, 'too_many_items', 'Payload has too many extra keys; extras were dropped.');
                    break;
                }
                $safeKey = is_string($key) ? str_replace("\0", '', $key) : $key;
                $childPath = $path === '' ? (string) $safeKey : $path . '.' . $safeKey;
                $order[$safeKey] = $positions[$key] ?? PHP_INT_MAX;
                $result[$safeKey] = $this->stripNullBytesDeep($value, $depth + 1, $childPath, $errors);
                $count++;
            }
        }

        // Otherwise: strict mode — anything left in $remaining is silently dropped.

        // Re-emit in input order. PHP 8's sort is stable, so applied defaults (PHP_INT_MAX) keep
        // their relative schema order at the end.
        uksort($result, static fn($a, $b): int => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));

        return [$result, $errors];
    }

    /**
     * @return array{0: mixed, 1: list<array{path:string,code:string,message:string}>}
     */
    private function sanitizeField(array $def, $rawValue, string $path, int $depth): array
    {
        $type = $def['type'] ?? 'raw';

        if ($type === 'array') {
            return $this->sanitizeArrayNode($def, $rawValue, $path, $depth);
        }

        // 'mixed' — this build does not know the value's shape. An array is kept as an opaque
        // subtree: still bounded by maxDepth/maxNodes and null-byte scrubbed, but otherwise
        // byte-identical, key order included. A scalar falls through to the normal scalar path.
        // Needed wherever one dynamic map legitimately holds both — the preferences blob has
        // scalars (ai_priority) alongside arrays (m_queries, whitelisted_domains, my_props), and
        // rejecting either would lose real user data.
        if ($type === 'mixed' && is_array($rawValue)) {
            $mixedErrors = [];
            $cleaned = $this->stripNullBytesDeep($rawValue, $depth, $path, $mixedErrors);
            // optional sanitizer for the whole subtree (e.g. Schemas::stripTagsDeep)
            if (isset($def['arraySanitizer']) && is_callable($def['arraySanitizer'])) {
                $cleaned = call_user_func($def['arraySanitizer'], $cleaned);
            }
            return [$cleaned, $mixedErrors];
        }

        if (is_array($rawValue)) {
            return [null, [$this->error($path, 'invalid_type', 'Expected a scalar value but got an array.')]];
        }

        [$value, $castError] = $this->castScalar($type, $rawValue, $def, $path);
        if ($castError !== null) {
            return [null, [$castError]];
        }

        if (is_string($value)) {
            // Characters, not bytes, consistently on both sides: a byte-length test paired with a
            // character-wise truncation silently did nothing for multibyte input (a 150-char/300-byte
            // string "exceeded" a 200 limit, then mb_substr(0, 200) handed all 150 chars straight back).
            $limit = (int) ($def['maxLength'] ?? $this->defaultMaxStringLength);
            if (mb_strlen($value) > $limit) {
                $value = mb_substr($value, 0, $limit);
            }
        }

        $validationError = $this->applyValidator($def, $value, $path);
        if ($validationError !== null) {
            return [null, [$validationError]];
        }

        return [$value, []];
    }

    /**
     * @return array{0: mixed, 1: ?array{path:string,code:string,message:string}}
     */
    private function castScalar(string $type, $rawValue, array $def, string $path): array
    {
        if (isset($def['sanitizer']) && is_callable($def['sanitizer'])) {
            return [($def['sanitizer'])($rawValue), null];
        }

        switch ($type) {
            case 'key':
                return [sanitize_key((string) $rawValue), null];
            case 'text':
                return [sanitize_text_field((string) $rawValue), null];
            case 'textarea':
                return [sanitize_textarea_field((string) $rawValue), null];
            case 'email':
                return [sanitize_email((string) $rawValue), null];
            case 'url':
                return [esc_url_raw((string) $rawValue), null];
            case 'int':
                return [intval($rawValue), null];
            case 'float':
                return [floatval($rawValue), null];
            case 'bool':
                $bool = filter_var($rawValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                return [$bool === null ? false : $bool, null];
            case 'raw':
            case 'mixed':
                return [$rawValue, null];
            default:
                return [null, $this->error($path, 'unknown_type', "Unknown schema type '{$type}'.")];
        }
    }

    /**
     * @return ?array{path:string,code:string,message:string}
     */
    private function applyValidator(array $def, $value, string $path): ?array
    {
        if (!isset($def['validator'])) {
            return null;
        }

        $validator = $def['validator'];

        if (is_callable($validator)) {
            $result = $validator($value);
            if ($result === true) {
                return null;
            }
            return $this->error($path, 'invalid_value', is_string($result) ? $result : 'Value failed validation.');
        }

        if (!is_array($validator)) {
            return null;
        }

        if (isset($validator['enum']) && !in_array($value, $validator['enum'], true)) {
            return $this->error($path, 'invalid_enum', 'Value is not in the allowed list.');
        }

        if (isset($validator['regex'])) {
            if (!is_string($value) || preg_match($validator['regex'], $value) !== 1) {
                return $this->error($path, 'invalid_format', 'Value does not match the required format.');
            }
        }

        if (isset($validator['range'])) {
            // A non-numeric value fails a range constraint rather than silently escaping it. Skipping the
            // check on !is_numeric() meant a declared bound quietly stopped applying for exactly the
            // inputs most likely to be hostile — "abc", or the empty string a failed esc_url_raw()
            // /sanitize_email() leaves behind on a 'raw'-typed field.
            if (!is_numeric($value)) {
                return $this->error($path, 'not_numeric', 'Value must be numeric to satisfy the allowed range.');
            }
            $min = $validator['range']['min'] ?? null;
            $max = $validator['range']['max'] ?? null;
            if (($min !== null && $value < $min) || ($max !== null && $value > $max)) {
                return $this->error($path, 'out_of_range', 'Value is outside the allowed range.');
            }
        }

        return null;
    }

    /**
     * Recursively strips null bytes from passthrough data without otherwise altering it.
     *
     * When a guard trips, the subtree is returned UNMODIFIED and an error is recorded — never replaced
     * with null. Passthrough data is opaque settings this edition doesn't interpret, so leaving a deep
     * subtree uncleaned is a far smaller problem than silently destroying a customer's saved settings in
     * the one path documented as lossless. Recursion still stops, so the guard's real job (bounding work)
     * is done either way, and the caller sees the error instead of a clean-looking empty result.
     *
     * @param list<array{path:string,code:string,message:string}> $errors
     */
    private function stripNullBytesDeep($value, int $depth, string $path, array &$errors)
    {
        if (is_string($value)) {
            return str_replace("\0", '', $value);
        }

        if (!is_array($value)) {
            return $value;
        }

        if ($depth > $this->maxDepth) {
            $errors[] = $this->error($path, 'max_depth_exceeded', 'Nesting too deep to clean; stored unmodified.');
            return $value;
        }

        $this->nodeCount++;
        if ($this->nodeCount > $this->maxNodes) {
            $errors[] = $this->error($path, 'payload_too_large', 'Payload too large to clean; stored unmodified.');
            return $value;
        }

        $out = [];
        foreach ($value as $key => $item) {
            $safeKey = is_string($key) ? str_replace("\0", '', $key) : $key;
            $out[$safeKey] = $this->stripNullBytesDeep($item, $depth + 1, $path . '.' . $safeKey, $errors);
        }
        return $out;
    }

    /**
     * Does a raw (pre-sanitization) wildcard key match the definition's 'keyMatch' filter?
     * Accepts a callable(string): bool or a regex string.
     */
    private function keyMatches($keyMatch, string $key): bool
    {
        if (is_callable($keyMatch)) {
            return (bool) $keyMatch($key);
        }
        if (is_string($keyMatch)) {
            return preg_match($keyMatch, $key) === 1;
        }
        return true;
    }

    /**
     * @return array{path:string,code:string,message:string}
     */
    private function error(string $path, string $code, string $message): array
    {
        return ['path' => $path, 'code' => $code, 'message' => $message];
    }
}
