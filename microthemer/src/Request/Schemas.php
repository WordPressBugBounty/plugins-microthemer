<?php

declare(strict_types=1);

namespace Microthemer\Request;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Named request schemas — one factory per endpoint.
 *
 * Keeping the schema next to the sanitizer rather than inline at the call site means the endpoint and
 * its tests share a single definition, and gives the endpoint migration (T-wpm3ah) one place to
 * accumulate rather than scattering array literals through Admin.php / AdminInitTrait.php. This is the
 * seed of the Request layer in plan §8.
 */
class Schemas
{
    /**
     * Schema for the `save_multiple_preferences` AJAX action (AdminInitTrait::microthemer_ajax_actions).
     *
     * Preference keys are dynamic, so this is a wildcard map. Values are genuinely mixed: plain scalars
     * (ai_priority), user-authored strings that must not be mangled (mt_rich_text_code holds CSS/JSON),
     * and opaque nested arrays (m_queries, whitelisted_domains, my_props).
     *
     * @param bool $allInt the stricter mode the JS advertises via MTsave.save_multiple_preferences(x, 1)
     */
    public static function preferences(bool $allInt = false): Schema
    {
        $valueDef = $allInt
            // Every value must be a scalar number; arrays are rejected with a typed error.
            ? ['type' => 'float']
            : [
                'type' => 'mixed',
                // Numeric strings become floats (the pre-schema semantics); other strings have tags
                // and control characters removed but are otherwise kept - sanitize_text_field
                // would corrupt user CSS/JSON (e.g. mt_rich_text_code).
                'sanitizer' => static function ($value) {
                    if (is_numeric($value)) {
                        return floatval($value);
                    }
                    return is_string($value) ? self::stripTags($value) : $value;
                },
            ];

        return new Schema([
            'pref_array' => [
                'type' => 'array',
                // known structured preferences are typed per field
                'schema' => $allInt ? [] : [
                    'm_queries' => self::mediaQueryMap(),
                    'enq_js' => self::enqueuedScriptMap(),
                ],
                'wildcard' => $valueDef,
                // Not sanitize_key(): it lowercases, turning lastMultiTab into lastmultitab.
                'keySanitizer' => static function ($key) {
                    return preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $key);
                },
            ],
        ]);
    }

    /**
     * Whole preference arrays from the setup screen's import and from a revision restore. They hold
     * every kind of preference, including code, so values are kept as they are, apart from the
     * structured preferences that reach the front end (media queries, enqueued scripts), which get
     * the same per-field checks as the forms. (A schema over the whole array would cap its size.)
     */
    public static function sanitizeImportedPreferences(array $prefs): array
    {
        $structured = array_intersect_key($prefs, ['m_queries' => 1, 'enq_js' => 1]);
        if (!$structured) {
            return $prefs;
        }

        $checked = (new Schema([
            'prefs' => [
                'type' => 'array',
                'schema' => [
                    'm_queries' => self::mediaQueryMap(),
                    'enq_js' => self::enqueuedScriptMap(),
                ],
            ],
        ]))->sanitize(['prefs' => $structured]);

        foreach (array_keys($structured) as $key) {
            if (isset($checked['value']['prefs'][$key])) {
                $prefs[$key] = $checked['value']['prefs'][$key];
            } else {
                unset($prefs[$key]);
            }
        }

        // a script whose handle failed its check is dropped, as the preferences form does
        if (isset($prefs['enq_js']) && is_array($prefs['enq_js'])) {
            $prefs['enq_js'] = array_filter($prefs['enq_js'], static function ($script) {
                return is_array($script) && !empty($script['display_name']);
            });
        }

        return $prefs;
    }

    /**
     * A value this build does not interpret: arrays are kept opaquely (depth/size bounded,
     * null-byte scrubbed by Schema), scalars are kept verbatim apart from null-byte scrubbing.
     * Used for user-authored CSS values/config where sanitize_text_field() would corrupt real
     * data (percent-encoded data-URIs in mask images, CSS escapes like \f101, selector syntax).
     */
    private static function opaque(): array
    {
        return [
            'type' => 'mixed',
            'sanitizer' => static function ($value) {
                return is_string($value) ? str_replace("\0", '', $value) : $value;
            },
        ];
    }

    /**
     * Like opaque(), for user-authored CSS values and interface config: arrays keep their shape,
     * but HTML tags and control characters are removed from every key and string (CSS needs
     * neither; a lone "<" or ">" stays). opaque() itself is kept for code (npm file content).
     */
    private static function textOpaque(): array
    {
        return [
            'type' => 'mixed',
            'sanitizer' => static function ($value) {
                return is_string($value) ? self::stripTags($value) : $value;
            },
            'arraySanitizer' => [self::class, 'stripTagsDeep'],
        ];
    }

    /** stripTags() applied to every string and key of an array. */
    public static function stripTagsDeep($data)
    {
        if (is_string($data)) {
            return self::stripTags($data);
        }
        if (!is_array($data)) {
            return $data;
        }
        $clean = [];
        foreach ($data as $key => $value) {
            $clean[is_int($key) ? $key : self::stripTags((string) $key)] = self::stripTagsDeep($value);
        }
        return $clean;
    }

    /** Preference-style key names: preserve case (lastMultiTab), allow word chars and hyphens. */
    private static function prefKeySanitizer(): callable
    {
        return static function ($key) {
            return preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $key);
        };
    }

    /**
     * Schema for the workspace preferences form (Admin::process_preferences_form) and the
     * import-preferences array posted with stylesheet imports. Same wildcard shape as
     * preferences() but with NO numeric coercion — the form route never coerced, and changing
     * stored value types on this path would dirty every subsequent comparison.
     * Wrap the posted array under 'tvr_preferences' when calling sanitize().
     */
    public static function preferencesForm(): Schema
    {
        return new Schema([
            'tvr_preferences' => [
                'type' => 'array',
                'schema' => [
                    'm_queries' => self::mediaQueryMap(),
                    'enq_js' => self::enqueuedScriptMap(),
                    // a URL or root-relative path. sanitize_text_field() would strip %XX sequences
                    // (caf%C3%A9), so this is not the text wildcard.
                    'preview_url' => [
                        'type' => 'raw',
                        'maxLength' => 2000,
                        'sanitizer' => static function ($value) {
                            return is_scalar($value) ? self::stripTags(trim((string) $value)) : '';
                        },
                    ],
                    // default unit per property: group => property => unit (e.g. px, rem, %)
                    'new_css_units' => [
                        'type' => 'array',
                        'keySanitizer' => self::prefKeySanitizer(),
                        'wildcard' => [
                            'type' => 'array',
                            'keySanitizer' => self::prefKeySanitizer(),
                            'wildcard' => ['type' => 'text', 'maxLength' => 20],
                        ],
                    ],
                ],
                // every other form field is a toggle, number, unit, handle list or URL
                'wildcard' => ['type' => 'text', 'maxLength' => 2000],
                'keySanitizer' => self::prefKeySanitizer(),
            ],
        ]);
    }

    /** Text shown in the interface (e.g. a tab label like "< 480"): tags and control characters removed. */
    private static function uiText(int $maxLength = 200): array
    {
        return [
            'type' => 'raw',
            'maxLength' => $maxLength,
            'sanitizer' => static function ($value) {
                return is_scalar($value) ? trim(self::stripTags((string) $value)) : '';
            },
        ];
    }

    /**
     * A media or container query condition. Written into stylesheets as "@media <query> {", so
     * braces and semicolons (which can't appear in a valid query) are removed: they could only
     * end the block early and add rules. "<" stays for range syntax, e.g. (400px <= width).
     */
    private static function cssQuery(): array
    {
        return [
            'type' => 'raw',
            'maxLength' => 500,
            'sanitizer' => static function ($value) {
                return is_scalar($value)
                    ? trim(str_replace(['{', '}', ';'], '', self::stripTags((string) $value)))
                    : '';
            },
        ];
    }

    /** A fragment of CSS (e.g. part of a selector): tags removed, and braces/semicolons, which could end a rule. */
    public static function cssFragment($value): string
    {
        return is_scalar($value) ? str_replace(['{', '}', ';'], '', self::stripTags((string) $value)) : '';
    }

    /**
     * Remove HTML tags/comments and control characters.
     * A "<" only starts a tag when a real tag name follows (optional attributes, then ">"),
     * so CSS and JS comparisons such as (400px < width) or i < items stay intact.
     * Browsers also open a tag for <img/src=…> or an unclosed <img src=x onerror=…, so any "<" that
     * is still followed by a letter, "/", "!" or "?" gets a space after it: "(400px<width)" becomes
     * "(400px< width)", which means the same in CSS and JS but can never start an HTML tag.
     */
    public static function stripTags(string $value): string
    {
        $value = preg_replace(
            '#<!--.*?-->|</?[a-z][a-z0-9:-]*(?:\s[^<>]*?)?/?>|<![a-z][^<>]*>#is',
            '',
            $value
        );
        $value = preg_replace('#<(?=[a-z/!?])#i', '< ', (string) $value);
        return self::stripControlChars((string) $value);
    }

    /** Null bytes and other control characters, keeping tabs and newlines. */
    public static function stripControlChars(string $value): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
    }

    /** The responsive tabs: media query key => tab settings. */
    public static function mediaQueryMap(): array
    {
        $int = ['type' => 'int'];
        $unit = ['type' => 'key', 'validator' => ['enum' => ['px', 'em', 'rem']]];

        return [
            'type' => 'array',
            'maxItems' => 100,
            'keySanitizer' => self::prefKeySanitizer(),
            'wildcard' => [
                'type' => 'array',
                'schema' => [
                    'label' => self::uiText(),
                    'query' => self::cssQuery(),
                    // a width (e.g. 480px) or a page builder view (e.g. builder.gutenberg.mobile)
                    'site_preview_width' => [
                        'type' => 'text',
                        'maxLength' => 60,
                        'validator' => ['regex' => '/^[A-Za-z0-9_.%\- ]*$/'],
                    ],
                    'hide' => $int,
                    'container' => $int,
                    'min' => $int,
                    'max' => $int,
                    'min_unit' => $unit,
                    'max_unit' => $unit,
                ],
            ],
        ];
    }

    /** Scripts WordPress registers that the site should enqueue: key => {display_name: handle, disabled}. */
    public static function enqueuedScriptMap(): array
    {
        return [
            'type' => 'array',
            'maxItems' => 100,
            'keySanitizer' => self::prefKeySanitizer(),
            'wildcard' => [
                'type' => 'array',
                'schema' => [
                    'display_name' => [
                        'type' => 'text',
                        'required' => true,
                        'maxLength' => 100,
                        'validator' => ['regex' => '/^[A-Za-z0-9_.\-]+$/'],
                    ],
                    'disabled' => ['type' => 'int'],
                ],
            ],
        ];
    }

    /**
     * The error report the interface sends (LogTrait::maybeSendReport): only these fields are used,
     * each typed. options_live/preferences are JSON strings of the interface state, decoded later.
     */
    public static function errorReport(): Schema
    {
        $int = ['type' => 'int'];
        $sendLog = [
            'type' => 'array',
            'schema' => [
                'last_sent' => ['type' => 'text', 'maxLength' => 50],
                'sends_today' => $int,
            ],
        ];

        return new Schema([
            'reporting' => [
                'type' => 'array',
                'schema' => [
                    'anonymous_id' => $int,
                    'data' => $sendLog,
                    'file' => [
                        'type' => 'array',
                        'schema' => [
                            'last_sent' => ['type' => 'text', 'maxLength' => 50],
                            'sends_today' => $int,
                            'unique_errors' => [
                                'type' => 'array',
                                'maxItems' => 200,
                                'keySanitizer' => self::prefKeySanitizer(),
                                'wildcard' => ['type' => 'text', 'maxLength' => 50],
                            ],
                        ],
                    ],
                    'permission' => [
                        'type' => 'array',
                        'schema' => ['file' => $int, 'data' => $int, 'contact' => $int],
                    ],
                ],
            ],
            'error' => ['type' => 'text', 'maxLength' => 500],
            'error_message' => ['type' => 'textarea', 'maxLength' => 2000],
            'error_key' => ['type' => 'text', 'maxLength' => 200],
            'error_stack' => ['type' => 'textarea', 'maxLength' => 10000],
            'domain' => ['type' => 'text', 'maxLength' => 200],
            'vendor' => ['type' => 'text', 'maxLength' => 200],
            'vendor_type' => ['type' => 'key', 'maxLength' => 50],
            'context' => [
                'type' => 'array',
                'maxItems' => 50,
                'keySanitizer' => self::prefKeySanitizer(),
                'wildcard' => ['type' => 'textarea', 'maxLength' => 2000],
            ],
            'options_live' => self::jsonString(),
            'preferences' => self::jsonString(),
            'onboard' => $int,
            'isAIFeedback' => $int,
            'preview' => $int,
        ]);
    }

    /** A JSON document posted as a string (kept as a string; invalid JSON is dropped). */
    private static function jsonString(): array
    {
        return [
            'type' => 'raw',
            'maxLength' => 5000000,
            'validator' => static function ($value) {
                return is_string($value) && is_array(json_decode($value, true));
            },
        ];
    }

    /** The public lazy-loading request (AjaxPublic): up to 50 content IDs. */
    public static function lazyContent(): Schema
    {
        return new Schema([
            'lazy_ids' => [
                'type' => 'array',
                'required' => true,
                'maxItems' => 50,
                'items' => ['type' => 'text', 'maxLength' => 100],
            ],
            // 0 asks for draft content, which AjaxPublic only serves to administrators
            'published' => ['type' => 'int'],
        ]);
    }

    /** The draft folder cookie as read back (AssetAuth): the folder slug and its loading logic. */
    public static function draftFolderCookie(): Schema
    {
        return new Schema([
            'slug' => [
                'type' => 'text',
                'required' => true,
                'maxLength' => 200,
                'validator' => ['regex' => '/^[\p{L}\p{N}_-]+$/u'],
            ],
            // a folder logic expression, evaluated by Logic (not PHP)
            'expr' => self::uiText(5000),
        ]);
    }

    /**
     * Settings data posted by the interface (a full save, partial save packages, imported CSS):
     * a tree keyed by folder, selector, media query, group and property slugs whose leaves are
     * CSS values, selectors and generated CSS. Null bytes, control characters and HTML tags are
     * removed from every key and string; a "<" that is not an HTML tag stays, as CSS and JS use
     * it. Two subtrees are exempt from tag stripping (control characters still go):
     * - HTML modification values (styles > html), which are HTML by design and belong to the
     *   Amender module, which the wp.org build does not include. A folder or selector whose
     *   slug happens to be "html" is not this subtree.
     * - Custom code: the full CSS/JS editors (non_section > js / hand_coded_css /
     *   hand_coded_css_compiled / code_editors), per-selector code (styles > code) and
     *   compiled_css. These are code, and stripping tags corrupts comparisons, SVG data URIs
     *   and HTML strings inside them. Builds without the code editors never persist edits to
     *   the editor fields (see SettingsTrait::keepStoredCustomCode()).
     */
    public static function sanitizeSettings($data, bool $inHtml = false, int $depth = 0, bool $inCode = false, $parentKey = null)
    {
        if ($depth > 40) {
            return null;
        }

        if (is_string($data)) {
            return ($inHtml || $inCode) ? self::stripControlChars($data) : self::stripTags($data);
        }

        if (!is_array($data)) {
            return is_scalar($data) || $data === null ? $data : null;
        }

        $clean = [];
        foreach ($data as $key => $value) {
            $cleanKey = is_int($key) ? $key : (
                ($inHtml || $inCode) ? self::stripControlChars((string) $key) : self::stripTags((string) $key)
            );
            $clean[$cleanKey] = self::sanitizeSettings(
                $value,
                $inHtml || self::isHtmlValue($key, $parentKey) || ($key === 'data' && self::targetsHtml($data)),
                $depth + 1,
                $inCode || self::isCodeValue($key, $parentKey) || ($key === 'data' && self::targetsCode($data)),
                is_int($key) ? $parentKey : $key
            );
        }

        return $clean;
    }

    /** styles > html holds Amender HTML. A folder or selector slug "html" does not. */
    private static function isHtmlValue($key, $parentKey): bool
    {
        return $parentKey === 'styles' && $key === 'html';
    }

    /**
     * Custom CSS/JS. compiled_css and fullCodeSelectors can sit on any selector (including
     * inside a media query); the editor fields only exist under non_section; per-selector
     * code is the styles > code group.
     */
    private static function isCodeValue($key, $parentKey): bool
    {
        if (!is_string($key)) {
            return false;
        }
        if ($key === 'compiled_css' || $key === 'fullCodeSelectors') {
            return true;
        }
        if ($parentKey === 'non_section' && in_array($key, [
            'js', 'hand_coded_css', 'hand_coded_css_compiled', 'code_editors',
        ], true)) {
            return true;
        }
        return $parentKey === 'styles' && $key === 'code';
    }

    /** Whether a partial save package item writes Amender HTML (styles, then html, in its trail). */
    private static function targetsHtml($item): bool
    {
        return self::trailHasPair(self::itemTrail($item), 'styles', 'html');
    }

    /** Whether a partial save package item writes custom code (see isCodeValue()). */
    private static function targetsCode($item): bool
    {
        $trail = self::itemTrail($item);
        $count = count($trail);
        if ($count === 0) {
            return false;
        }
        $last = $trail[$count - 1];
        $prev = $count > 1 ? $trail[$count - 2] : null;
        if ($last === 'compiled_css' || $last === 'fullCodeSelectors') {
            return true;
        }
        if ($prev === 'non_section' && in_array($last, [
            'js', 'hand_coded_css', 'hand_coded_css_compiled', 'code_editors',
        ], true)) {
            return true;
        }
        return self::trailHasPair($trail, 'styles', 'code');
    }

    /** Trail of a save-package item, including its key when the update names one. */
    private static function itemTrail($item): array
    {
        if (!is_array($item)) {
            return [];
        }
        $trail = (isset($item['trail']) && is_array($item['trail'])) ? array_values($item['trail']) : [];
        if (isset($item['key']) && is_string($item['key'])) {
            $trail[] = $item['key'];
        }
        return $trail;
    }

    /** Whether $trail contains $first immediately followed by $second. */
    private static function trailHasPair(array $trail, string $first, string $second): bool
    {
        $count = count($trail);
        for ($i = 1; $i < $count; $i++) {
            if ($trail[$i - 1] === $first && $trail[$i] === $second) {
                return true;
            }
        }
        return false;
    }

    /**
     * Schema for the `update_sug_values` JSON payload (AdminInitTrait): property-value
     * suggestion maps keyed by property category. Values are user-authored CSS strings and
     * nested suggestion arrays — opaque by design (see opaque()).
     */
    public static function sugValues(): Schema
    {
        $suggestionMap = [
            'type' => 'array',
            'wildcard' => self::textOpaque(),
            'keySanitizer' => self::prefKeySanitizer(),
        ];

        return new Schema([
            'sug_values' => $suggestionMap,
            'sug_variables' => self::textOpaque(),
            'synced_set' => $suggestionMap,
            'specific' => self::textOpaque(),
        ]);
    }

    /** An opaque config object wrapped under 'config' (see wrapJson()): sanitized keys, opaque values. */
    private static function wrappedOpaqueMap(): Schema
    {
        return new Schema([
            'config' => [
                'type' => 'array',
                'wildcard' => self::textOpaque(),
                'keySanitizer' => self::prefKeySanitizer(),
            ],
        ]);
    }

    /**
     * Schema for the `save_font_config` JSON payload: the google/typekit font config object
     * (families, filters, paging). Wrap the raw JSON under 'config' when calling (see wrapJson()).
     */
    public static function fontConfig(): Schema
    {
        return self::wrappedOpaqueMap();
    }

    /**
     * Schema for the `update_mt_layout` JSON payload: the workspace layout config (column
     * sizes, panel state). Wrap the raw JSON under 'config' when calling (see wrapJson()).
     */
    public static function layout(): Schema
    {
        return self::wrappedOpaqueMap();
    }

    /**
     * Schema for the draft-folder cookie JSON payload (updateDraftFolderCookie). Used for
     * validation only — the raw string is what gets stored in the cookie.
     * Wrap the raw JSON under 'config' when calling (see wrapJson()).
     */
    public static function draftFolder(): Schema
    {
        return self::wrappedOpaqueMap();
    }

    /**
     * Schema for `update_custom_paths`: the full preview custom-paths list posted to replace
     * the stored one. Entries are {value,label,item_id} objects, but legacy data may hold plain
     * path strings (the consumers handle both — see maybe_set_preview_url), so items stay
     * opaque rather than strictly shaped; paths/labels are user-authored URL fragments that may
     * carry percent-encoding. The list shape and size are enforced.
     * Wrap the raw JSON under 'list' when calling (see wrapJson()).
     */
    public static function customPaths(): Schema
    {
        return new Schema([
            'list' => [
                'type' => 'array',
                'maxItems' => 100,
                'items' => self::textOpaque(),
            ],
        ]);
    }

    /**
     * Schema for `update_default_unit`: {group, prop, unit}. group/prop become array indexes
     * in the saved my_props structure, so they are strictly cleaned and required; unit is a
     * short CSS unit string.
     */
    public static function defaultUnit(): Schema
    {
        return new Schema([
            'group' => [
                'type' => 'raw',
                'required' => true,
                'sanitizer' => self::prefKeySanitizer(),
                'maxLength' => 100,
            ],
            'prop' => [
                'type' => 'raw',
                'required' => true,
                'sanitizer' => self::prefKeySanitizer(),
                'maxLength' => 100,
            ],
            'unit' => [
                'type' => 'text',
                'maxLength' => 20,
                'default' => '',
            ],
        ]);
    }

    /**
     * Schema for `update_recent_custom_prefixes`: a JSON LIST of user-authored CSS selector
     * prefixes. Kept verbatim apart from null-byte scrubbing and length caps — selector syntax
     * (">", quotes, percent-encoding in attribute selectors) does not survive generic text
     * sanitizers. Wrap the list under 'list' when calling (see wrapJson()).
     */
    public static function recentCustomPrefixes(): Schema
    {
        $prefix = self::textOpaque();
        $prefix['maxLength'] = 1000;

        return new Schema([
            'list' => [
                'type' => 'array',
                'items' => $prefix,
                'maxItems' => 50,
            ],
        ]);
    }

    /**
     * Schema for the npm package install/uninstall payload (AdminContent::adjustNPMInstall).
     * These fields reach the filesystem and a CDN URL, so they are strictly validated:
     * - cdn: appended to https://cdn.jsdelivr.net/npm/ — restricted to npm path characters,
     *   no '..', '//', ':', '?', '#' or leading '/' so the request cannot escape that prefix.
     * - local: written/deleted under the plugin's npm vendor dir — same traversal rules plus
     *   a script/style file extension requirement.
     * - content: user-authored JS/CSS provided by the (capability-checked) admin; opaque.
     * Unknown item keys are dropped.
     */
    public static function npmPackages(): Schema
    {
        $safeRelPath = static function ($value) {
            if (!is_string($value) || $value === '' || mb_strlen($value) > 300) {
                return 'Expected a relative path up to 300 characters.';
            }
            if (strpos($value, '..') !== false || strpos($value, '//') !== false) {
                return 'Path traversal is not allowed.';
            }
            if (preg_match('#^[A-Za-z0-9_@+][A-Za-z0-9_@+.\-/]*$#', $value) !== 1) {
                return 'Path contains disallowed characters.';
            }
            return true;
        };

        return new Schema([
            'packages' => [
                'type' => 'array',
                'maxItems' => 100,
                'items' => [
                    'type' => 'array',
                    'schema' => [
                        'package' => ['type' => 'text', 'maxLength' => 214],
                        'cdn' => [
                            'type' => 'text',
                            'validator' => $safeRelPath,
                        ],
                        'local' => [
                            'type' => 'text',
                            'validator' => static function ($value) use ($safeRelPath) {
                                $base = $safeRelPath($value);
                                if ($base !== true) {
                                    return $base;
                                }
                                if (preg_match('/\.(m?js|css)$/i', $value) !== 1) {
                                    return 'Local file must be a .js, .mjs or .css file.';
                                }
                                return true;
                            },
                        ],
                        'content' => self::opaque(),
                        'install' => ['type' => 'bool'],
                        'isAddon' => ['type' => 'bool'],
                        'rootName' => [
                            'type' => 'text',
                            'maxLength' => 214,
                            'validator' => ['regex' => '/^[A-Za-z0-9._\-]+$/'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Schema for `update_npm_dependencies`: maps of npm package name => version/config saved
     * to preferences. Keys keep legitimate npm name characters (@scope/name, dots, hyphens).
     */
    public static function npmDependencies(): Schema
    {
        $depMap = [
            'type' => 'array',
            'wildcard' => self::opaque(),
            'maxItems' => 200,
            'keySanitizer' => static function ($key) {
                return preg_replace('#[^A-Za-z0-9_@.\-/]#', '', (string) $key);
            },
        ];

        return new Schema([
            'npm_dependencies' => $depMap,
            'npm_dependencies_in_use' => $depMap,
        ]);
    }

    /**
     * Validation schema for an imported design pack / raw CSS JSON blob
     * (stylesheet_import_json). The blob is user-authored settings that must round-trip
     * byte-exact (image path keys are replaced by exact string match downstream), so this
     * schema VALIDATES — size caps, JSON well-formedness, depth/node bounds, null-byte scan —
     * and the caller keeps using the original string when no errors are reported.
     * Wrap under 'pack' when calling (see wrapJson()).
     */
    public static function packImport(): Schema
    {
        return new Schema(
            [
                'pack' => [
                    'type' => 'array',
                    'passthrough' => true,
                    'maxItems' => 2000,
                ],
            ],
            [
                'maxNodes' => 200000,
                'maxDepth' => 30,
                'maxJsonBytes' => 20971520, // 20 MB — packs embed data-URI images
            ]
        );
    }

    /**
     * Helper for schemas whose natural payload root is a list or an opaque map: wraps the raw
     * (still-slashed) JSON string under a single named key so it can go through
     * Schema::sanitizeJson(). The added wrapper contains no backslashes, so unslashing inside
     * sanitizeJson() is unaffected.
     */
    public static function wrapJson(string $rawJson, string $key): string
    {
        return '{"' . $key . '":' . $rawJson . '}';
    }
}
