<?php

namespace Uay\YEntityGeneratorBundle\Utils;

abstract class TextUtil
{
    /**
     * Pluralizes a word if quantity is not one.
     *
     * @param int $quantity Number of items
     * @param string $singular Singular form of word
     * @param string $plural Plural form of word; function will attempt to deduce plural form from singular if not provided
     * @return string Pluralized word if quantity is not one, otherwise singular
     * @see https://stackoverflow.com/a/16925755/3359418
     */
    public static function pluralize(int $quantity, string $singular, string $plural = null): string
    {
        if ($quantity === 1 || $singular === '') {
            return $singular;
        }

        if ($plural !== null) {
            return $plural;
        }

        $last_letter = strtolower($singular[\strlen($singular) - 1]);
        switch ($last_letter) {
            case 'y':
                return substr($singular, 0, -1) . 'ies';
            case 's':
                return $singular . 'es';
            default:
                return $singular . 's';
        }
    }
}
