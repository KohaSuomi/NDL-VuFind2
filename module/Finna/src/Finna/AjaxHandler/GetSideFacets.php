<?php

/**
 * "Get Side Facets" AJAX handler
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2018-2019.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\AjaxHandler;

use VuFind\Search\Base\Results;
use VuFind\Search\RecommendListener;

use function is_callable;

/**
 * "Get Side Facets" AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetSideFacets extends \VuFind\AjaxHandler\GetSideFacets
{
    /**
     * Get the result count for a checkbox facet
     *
     * @param string  $facet   Facet
     * @param Results $results Search results
     *
     * @return int|null
     */
    protected function getCheckboxFacetCount($facet, Results $results)
    {
        $checkboxFacets = $results->getParams()->getCheckboxFacets();
        $delimitedFacets = $results->getParams()->getOptions()
            ->getDelimitedFacets(true);
        foreach ($checkboxFacets as $checkboxFacet) {
            if ($facet !== $checkboxFacet['filter']) {
                continue;
            }
            [$field, $value] = explode(':', $facet, 2);
            $checkboxResults = $results->getFacetList(
                [$field => $value]
            );
            if (!isset($checkboxResults[$field]['list'])) {
                return null;
            }
            $count = 0;
            $truncate = substr($value, -1) === '*';
            if ($truncate) {
                $value = substr($value, 0, -1);
            }
            foreach ($checkboxResults[$field]['list'] as $item) {
                $itemValue = $item['value'];
                if ($delimiter = $delimitedFacets[$field] ?? '') {
                    [$itemValue] = explode($delimiter, $itemValue);
                }
                if (
                    $itemValue == $value
                    || ($truncate
                    && preg_match('/^' . $value . '/', $item['value']))
                    || ($item['value'] == 'true' && $value == '1')
                    || ($item['value'] == 'false' && $value == '0')
                ) {
                    $count += $item['count'];
                }
            }
            return $count;
        }
        return null;
    }

    /**
     * Perform search and return the results
     *
     * Finna: Request checkbox facet counts too
     *
     * @param array  $request Request params
     * @param string $index   Index of SideFacetsDeferred in configuration
     * @param string $loc     Location where SideFacetsDeferred is configured
     *
     * @return Results
     */
    protected function getFacetResults(array $request, $index, $loc)
    {
        $setupCallback = function ($runner, $params, $searchId) use ($index, $loc) {
            $listener = new RecommendListener(
                $this->recommendPluginManager,
                $searchId
            );
            $config = [];
            $rawConfig = $params->getOptions()
                ->getRecommendationSettings($params->getSearchHandler());
            $settings = explode(':', $rawConfig[$loc][$index] ?? '');
            if ($settings[0] === 'SideFacetsDeferred') {
                $settings[0] = 'SideFacets';
                $config[$loc][] = implode(':', $settings);
            }
            $listener->setConfig($config);
            $listener->attach($runner->getEventManager()->getSharedManager());

            $params->setLimit(0);
            $params->setCheckboxFacetCounts(true);
            if (is_callable([$params, 'setHierarchicalFacetLimit'])) {
                $params->setHierarchicalFacetLimit(-1);
            }
            $options = $params->getOptions();
            $options->disableHighlighting();
            $options->spellcheckEnabled(false);
        };

        $runner = $this->searchRunner;
        return $runner->run(
            $request,
            $request['searchClassId'] ?? DEFAULT_SEARCH_BACKEND,
            $setupCallback
        );
    }
}
