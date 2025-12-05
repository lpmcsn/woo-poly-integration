<?php

/**
 * LocaleNumbers.
 *
 * @autor Jonathan Moore <jonathan.moore@bcs.org>
 */

namespace Hyyan\WPI;

use Hyyan\WPI\Admin\Settings;
use Hyyan\WPI\Admin\Features;

class LocaleNumbers
{

    /**
     * Hook relevant WooCommerce filters to apply localisation according to Polylang locale.
     */
    public function __construct()
    {
        if (
                class_exists('\NumberFormatter') &&
                'on' === Settings::getOption('localenumbers', Features::getID(), 'on')
        ) {

            //localise standard price formatting arguments
            add_filter('wc_get_price_decimal_separator', array($this, 'getLocaleDecimalSeparator'), 10, 1);
            add_filter('wc_get_price_thousand_separator', array($this, 'getLocaleThousandSeparator'), 10, 1);
            add_filter('wc_price_args', array($this, 'filterPriceArgs'), 10, 1);

            //WooCommerce 3.1 unreleased checkin https://github.com/woocommerce/woocommerce/pull/15628
            add_filter('woocommerce_format_localized_decimal', array($this, 'getLocalizedDecimal'), 10, 2);
            //no additional override on finished price format as no currency paramber available
            //add_filter('woocommerce_format_localized_price', array($this, 'getLocalizedPrice'), 10, 2);
        }
    }

    /*
     * Filter WooCommerce pricing arguments to localize
     * see https://github.com/hyyan/woo-poly-integration/wiki/Price-Localization for notes
     *
     * @param Array $args   arguments used with wc_price
     *     'ex_tax_label'       => false,
     *      'currency'           => '',
     *      'decimal_separator'  => wc_get_price_decimal_separator(),
     *      'thousand_separator' => wc_get_price_thousand_separator(),
     *      'decimals'           => wc_get_price_decimals(),
     *      'price_format'       => get_woocommerce_price_format(),
     *
     * @return Array the arguments
     */

    public function filterPriceArgs( $args ) {
        // Extraire les valeurs nécessaires depuis $args
        $price   = $args['price'] ?? 0;
        $currency = $args['currency'] ?? 'EUR';
        $locale  = $args['locale'] ?? get_locale();

        // Extraire la locale de base (sans les paramètres @)
        $baseLocale = preg_replace('/@.*/', '', $locale);

        // Vérifier si la locale est valide
        if (empty($baseLocale) || !\NumberFormatter::create($baseLocale, \NumberFormatter::CURRENCY)) {
            $baseLocale = 'en_US'; // Fallback
        }

        // Créer le formateur avec la locale valide
        try {
            $formatter = new \NumberFormatter($baseLocale, \NumberFormatter::CURRENCY);

            // Si le paramètre @currency=EUR est présent, forcer le symbole €
            if (strpos($locale, '@currency=EUR') !== false) {
                $formatter->setSymbol(\NumberFormatter::CURRENCY_SYMBOL, '€');
            }

            // Appliquer le formatage
            $formatted = $formatter->formatCurrency($price, $currency);

            // Remplacer le prix formaté dans les arguments
            $args['formatted_price'] = $formatted;

        } catch (\Exception $e) {
            // En cas d'erreur, utiliser un formatage simple
            $args['formatted_price'] = number_format($price, 2, ',', ' ') . ' €';
        }

        return $args;
    }

    /*
     * get localized decimal separator
     *
     * @param string    $input WooCommerce configured value
     *
     * @return string  formatted number
     */

    public function getLocaleDecimalSeparator($separator)
    {
        $retval = $separator;
        //don't touch values on admin screens, save as plain number using woo defaults
        if ((!is_admin()) || isset($_REQUEST['get_product_price_by_ajax'])) {
            $locale = pll_current_language('locale');
            $a = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
            if ($a) {
                $locale_result = $a->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
                if ($locale_result) {
                    $retval = $locale_result;
                }
            }
        }
        return $retval;
    }

    /*
     * get localized thousand separator
     *
     * @param string    $input WooCommerce configured value
     *
     * @return string  formatted number
     */

    public function getLocaleThousandSeparator($separator)
    {
        $retval = $separator;
        //don't touch values on admin screens, save as plain number using woo defaults
        if (!is_admin()) {
            $a = new \NumberFormatter(pll_current_language('locale'), \NumberFormatter::DECIMAL);
            if ($a) {
                $retval = $a->getSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL);
            }
        }
        return $retval;
    }

    /*
     * get localized decimal
     *
     * @param string    $input WooCommerce configured value
     *
     * @return string  formatted number
     */

    public function getLocalizedDecimal($input)
    {
        // Default to return unmodified WooCommerce value
        $retval = $input;

        // Don't touch values on admin screens, save as plain number using Woo defaults
        if ((!is_admin()) || isset($_REQUEST['get_product_price_by_ajax'])) {
            $a = new \NumberFormatter(pll_current_language('locale'), \NumberFormatter::DECIMAL);
            if ($a && is_numeric($input)) {
                $retval = $a->format((float)$input);
            }
        }
        return $retval;
    }
}
