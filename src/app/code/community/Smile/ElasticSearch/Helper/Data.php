<?php
/**
 * Search helper
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Smile Searchandising Suite to newer
 * versions in the future.
 *
 * This work is a fork of Johann Reinke <johann@bubblecode.net> previous module
 * available at https://github.com/jreinke/magento-elasticsearch
 *
 * @category  Smile
 * @package   Smile_ElasticSearch
 * @author    Aurelien FOUCRET <aurelien.foucret@smile.fr>
 * @copyright 2013 Smile
 * @license   Apache License Version 2.0
 */
class Smile_ElasticSearch_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Allowed languages.
     * Example: array('en_US' => 'en', 'fr_FR' => 'fr')
     *
     * @var array
     */
    protected $_languageCodes = array();

    /**
     * Text field types.
     *
     * @var array
     */
    protected $_textFieldTypes = array(
        'text',
        'varchar',
    );

    /**
     * Returns cache lifetime in seconds.
     *
     * @return int
     */
    public function getCacheLifetime()
    {
        return Mage::getStoreConfig('core/cache/lifetime');
    }

    /**
     * Returns search engine config data.
     *
     * @param string $prefix Configuration prefix to be loaded
     * @param mixed  $store  Store we want the configuration for
     *
     * @return array
     */
    public function getEngineConfigData($prefix = '', $store = null)
    {
        $config = Mage::getStoreConfig('catalog/search', $store);
        $data = array();
        if ($prefix) {
            foreach ($config as $key => $value) {
                $matches = array();
                if (preg_match("#^{$prefix}(.*)#", $key, $matches)) {
                    $data[$matches[1]] = $value;
                }
            }
        } else {
            $data = $config;
        }

        return $data;
    }

    /**
     * Returns EAV config singleton.
     *
     * @return Mage_Eav_Model_Config
     */
    public function getEavConfig()
    {
        return Mage::getSingleton('eav/config');
    }

    /**
     * Returns language code of specified locale code.
     *
     * @param string $localeCode Locale we want the ES language code
     *
     * @return bool
     */
    public function getLanguageCodeByLocaleCode($localeCode)
    {
        $localeCode = (string) $localeCode;
        if (!$localeCode) {
            return false;
        }

        if (!isset($this->_languageCodes[$localeCode])) {
            $languages = $this->getSupportedLanguages();
            $this->_languageCodes[$localeCode] = false;
            foreach ($languages as $code => $locales) {
                if (is_array($locales)) {
                    if (in_array($localeCode, $locales)) {
                        $this->_languageCodes[$localeCode] = $code;
                    }
                } elseif ($localeCode == $locales) {
                    $this->_languageCodes[$localeCode] = $code;
                }
            }
        }

        return $this->_languageCodes[$localeCode];
    }

    /**
     * Returns store language code.
     *
     * @param mixed $store The store we want the language code for
     *
     * @return bool
     */
    public function getLanguageCodeByStore($store = null)
    {
        return $this->getLanguageCodeByLocaleCode($this->getLocaleCode($store));
    }

    /**
     * Returns store locale code.
     *
     * @param null $store The store we want the locale code for
     *
     * @return string
     */
    public function getLocaleCode($store = null)
    {
        return Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $store);
    }

    /**
     * Get suggest field name for a store
     *
     * @param string|int|Mage_Core_Model_Store $store The store (current store is used if null)
     *
     * @return string
     */
    public function getSuggestFieldName($store = null)
    {
        $languageCode = $this->getLanguageCodeByStore($store);
        return $this->getSuggestFieldNameByLanguageCode($languageCode);
    }

    /**
     * Get suggest field name for a locale
     *
     * @param string $localeCode The locale code (current store locale is used if null)
     *
     * @return string
     */
    public function getSuggestFieldNameByLocaleCode($localeCode = null)
    {
        $languageCode = $this->getLanguageCodeByLocaleCode($localeCode);
        return $this->getSuggestFieldNameByLanguageCode($languageCode);

    }

    /**
     * Get suggest field name for a language code
     *
     * @param string $languageCode The language code
     *
     * @return string
     */
    public function getSuggestFieldNameByLanguageCode($languageCode = null)
    {
        $languageCode = $languageCode !== null ? $languageCode : $this->getLanguageCodeByStore();
        $languageSuffix = $languageCode ? '_' . $languageCode : '';
        return 'suggest' . $languageSuffix;
    }

    /**
     * Returns search config data field value.
     *
     * @param string $field Name of the fied (ie: elasticsearch_servers)
     * @param mixed  $store Store we want the config for
     *
     * @return mixed
     */
    public function getSearchConfigData($field, $store = null)
    {
        $path = 'catalog/search/' . $field;

        return Mage::getStoreConfig($path, $store);
    }


    /**
     * Defines supported languages for snowball filter.
     *
     * @return array
     */
    public function getSupportedLanguages()
    {
        $default = array(
            /**
             * SnowBall filter based
             */
            // Danish
            'da' => 'da_DK',
            // Dutch
            'nl' => 'nl_NL',
            // English
            'en' => array('en_AU', 'en_CA', 'en_NZ', 'en_GB', 'en_US'),
            // Finnish
            'fi' => 'fi_FI',
            // French
            'fr' => array('fr_CA', 'fr_FR'),
            // German
            'de' => array('de_DE','de_DE','de_AT'),
            // Hungarian
            'hu' => 'hu_HU',
            // Italian
            'it' => array('it_IT','it_CH'),
            // Norwegian
            'nb' => array('nb_NO', 'nn_NO'),
            // Portuguese
            'pt' => array('pt_BR', 'pt_PT'),
            // Romanian
            'ro' => 'ro_RO',
            // Russian
            'ru' => 'ru_RU',
            // Spanish
            'es' => array('es_AR', 'es_CL', 'es_CO', 'es_CR', 'es_ES', 'es_MX', 'es_PA', 'es_PE', 'es_VE'),
            // Swedish
            'sv' => 'sv_SE',
            // Turkish
            'tr' => 'tr_TR',

            /**
             * Lucene class based
             */
            // Czech
            'cs' => 'cs_CZ',
            // Greek
            'el' => 'el_GR',
            // Thai
            'th' => 'th_TH',
            // Chinese
            'zh' => array('zh_CN', 'zh_HK', 'zh_TW'),
            // Japanese
            'ja' => 'ja_JP',
            // Korean
            'ko' => 'ko_KR'
        );

        return $default;
    }

    /**
     * Checks if configured engine is active.
     *
     * @return bool
     */
    public function isActiveEngine()
    {
        $engine = $this->getSearchConfigData('engine');
        if ($engine && Mage::getConfig()->getResourceModelClassName($engine)) {
            $model = Mage::getResourceSingleton($engine);
            return $model
                && $model instanceof Smile_ElasticSearch_Model_Resource_Engine_ElasticSearch
                && $model->test();
        }

        return false;
    }

    /**
     * Checks if specified attribute is indexable by search engine.
     *
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute Attribute to be tested
     *
     * @return bool
     */
    public function isAttributeIndexable($attribute)
    {
        if ($attribute->getBackendType() == 'varchar' && !$attribute->getBackendModel()) {
            return true;
        }

        if ($attribute->getBackendType() == 'int'
            && $attribute->getSourceModel() != 'eav/entity_attribute_source_boolean'
            && ($attribute->getIsSearchable() || $attribute->getIsFilterable() || $attribute->getIsFilterableInSearch())
        ) {
            return true;
        }

        if ($attribute->getIsSearchable() || $attribute->getIsFilterable() || $attribute->getIsFilterableInSearch()) {
            return true;
        }

        return false;
    }

    /**
     * Checks if specified attribute is using options.
     *
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute Attribute to be tested
     *
     * @return bool
     */
    public function isAttributeUsingOptions($attribute)
    {
        $model = Mage::getModel($attribute->getSourceModel());

        return $attribute->usesSource() &&
               $attribute->getBackendType() == 'int' &&
               $model instanceof Mage_Eav_Model_Entity_Attribute_Source_Table;
    }

    /**
     * Checks if debug mode is enabled.
     *
     * @return bool
     */
    public function isDebugEnabled()
    {
        $config = $this->getEngineConfigData();

        return array_key_exists('enable_debug_mode', $config) && $config['enable_debug_mode'];
    }

    /**
     * Method that can be overriden for customing product data indexation.
     *
     * @param array  $index     Data to be indexed
     * @param string $separator Separator used into the index
     *
     * @return array
     */
    public function prepareIndexData($index, $separator = null)
    {
        return $index;
    }

    /**
     * Forces error display.
     *
     * @param string $error Error to be displayed
     *
     * @return void
     */
    public function showError($error)
    {
        echo Mage::app()->getLayout()->createBlock('core/messages')
            ->addError($error)->getGroupedHtml();
    }

    /**
     * Indicates if the current Magento instance is a Enterprise one.
     *
     * @return bool
     */
    public function isEnterpriseSupportEnabled()
    {
        return Mage::helper('core')->isModuleEnabled('Enterprise_CatalogSearch');
    }
}