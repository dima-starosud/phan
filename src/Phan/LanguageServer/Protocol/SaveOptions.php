<?php

namespace Phan\LanguageServer\Protocol;

/**
 * TODO: Contribute to php-language-server?
 * Based on SaveOptions description in
 * https://microsoft.github.io/language-server-protocol/specification
 */
class SaveOptions
{
    /**
     * @var bool|null
     * The client is supposed to include the content on save.
     * @suppress PhanWriteOnlyPublicProperty (used by AdvancedJsonRpc)
     */
    public $includeText;
}
