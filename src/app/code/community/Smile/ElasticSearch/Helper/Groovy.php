<?php
/**
 * ElasticSearch Groovy expressions helper
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
class Smile_ElasticSearch_Helper_Groovy extends Smile_ElasticSearch_Helper_Data
{
    /**
     * Generate a groovy expression that test if all parts of a text are into a field.
     * It is mostly used by facet autocomplete to post filter faceting results of multivalued attributes.
     *
     * @param string  $text     Text to be searched.
     * @param string  $field    Field to execute the search on.
     * @param boolean $isPrefix Prefix search enabled (ex: "blu" will match "Blue").
     * @param string  $operator Search operator (&& by default, you can also use ||).
     *
     * @return string|false
     */
    public function buildTextMatchRegex($text, $field, $isPrefix = true, $operator = "&&")
    {
        $script = false;
        $queryTokens = Mage::helper('core/string')->splitWords($text, true, 0, '\P{L}');
        $regexes = array();

        foreach (array_values($queryTokens) as $tokenPosition => $token) {
            if (!empty($token)) {
                $regex = '/(?i)((.*\W+)|^)' . $token;
                if ($tokenPosition == count($queryTokens)- 1 && $isPrefix) {
                    $regex = $regex . '.*/';
                } else {
                    $regex = $regex . '((\W+.*)|$)/';
                }
            }
            $regexes[] = sprintf('%s ==~ %s', $field, $regex);
        }
        if (!empty($regexes)) {
            $script = implode($operator, $regexes);
        }

        return $script;
    }
}