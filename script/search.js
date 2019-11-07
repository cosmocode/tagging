jQuery(function () {
    /**
     * Add tag search parameter to all links in the advanced search tools
     *
     * This duplicates the solution from the watchcycle plugin, and should also be replaced
     * with a DokuWiki event, which does not exist yet, but should handle extending search tools.
     */
    const $advancedOptions = jQuery('.search-results-form .advancedOptions');
    if (!$advancedOptions.length) {
        return;
    }

    /**
     * Extracts the value of a given parameter from the URL querystring
     *
     * taken via watchcycle from https://stackoverflow.com/a/31412050/3293343
     * @param param
     * @returns {*}
     */
    function getQueryParam(param) {
        location.search.substr(1)
            .split("&")
            .some(function(item) { // returns first occurence and stops
                return item.split("=")[0] === param && (param = item.split("=")[1])
            });
        return param
    }

    if (getQueryParam('tagging-logic') === 'and') {
        $advancedOptions.find('a').each(function (index, element) {
            const $link = jQuery(element);
            // do not override parameters in our own links
            if ($link.attr('href').indexOf('tagging-logic') === -1) {
                $link.attr('href', $link.attr('href') + '&tagging-logic=and');
            }
        });
    }


    /* **************************************************************************
     * Search filter
     * ************************************************************************ */

    const $filterContainer = jQuery('#plugin__tagging-tags');
    const $resultLinks = jQuery('div.search_fullpage_result dt a:not([class])');

    /**
     * Returns the filter ul
     *
     * @param {*} tags
     * @param {string[]} filters
     * @returns {jQuery}
     */
    function buildFilter(tags, filters) {
        const lis = [];
        let i = 0;

        // when tag search has no results, build the filter dropdown anyway but from tags in query
        if (Object.keys(tags).length === 0 && filters.length > 0) {
            for (const key of filters) {
                tags[key] = 0;
            }
        }

        for (const tag in tags) {
            let checked = filters.includes(tag) ? 'checked="checked"' : '';

            lis.push(` <li>
                <input name="tagging[]" type="checkbox" value="${tag}" id="__tagging-${i}" ${checked}>
                <label for="__tagging-${i}" title="${tag}">
                    ${tag} (${tags[tag]})
                </label>
            </li>`);
            i++;
        }

        if (lis.length) {
            $filterContainer.find('div.current').addClass('changed');
        } else {
            lis.push(`<li>${LANG.plugins.tagging.search_nofilter}</li>`);
        }

        return jQuery('<ul aria-expanded="false">' + lis.join('') + '</ul>');
    }

    /**
     * Collects tags from results list
     *
     * @returns {*}
     */
    function getTagsFromResults() {
        const tags = [];
        $resultLinks.toArray().forEach(function(link) {
            const text = jQuery(link).text();
            if (text.charAt(0) === '#') {
                const tag = text.replace('#', '');
                tags.push(tag);
            }
        });

        return tags.sort().reduce(function (allTags, tag) {
            if (tag in allTags) {
                allTags[tag]++;
            }
            else {
                allTags[tag] = 1;
            }
            return allTags;
        }, {});
    }

    /**
     * Returns query from the main search form, ignoring quicksearch.
     *
     * @returns {jQuery}
     */
    function getQueryElement() {
        return jQuery('#dokuwiki__content input[name="q"]');
    }

    /**
     * Returns an array of all tags found in search form input
     *
     * @returns {string[]}
     */
    function getFiltersFromQuery() {
        const parts = getQueryElement().val().split(' ');
        let filters = parts.filter(function (part) {
            return part.charAt(0) === '#';
        });

        return filters.map(function (tag) {
            return tag.replace('#', '');
        });
    }

    /**
     * Called when a tag filter is updated. Manipulates query by adding or removing the selected tag.
     *
     * @param {string} tag
     */
    function toggleTag(tag) {
        tag = '#' + tag;
        const $q = getQueryElement();
        const q = $q.val();
        const isFilter = q.indexOf(tag) > -1;

        if (isFilter) {
            $q.val(q.replace(tag, ''));
        } else {
            $q.val(q.trim() + ' ' + tag);
        }
    }

    /**
     * Restore tags in search links
     *
     * @param {jQuery} $searchLinks
     */
    function addTagsToSearchLinks($searchLinks) {
        const tags = getFiltersFromQuery();
        if (tags.length === 0) {
            return;
        }

        $searchLinks.each(function () {
            $link = jQuery(this);
            const qParam = $link[0]['href'].match(/q=[^&]*/)[0];
            $link[0]['href'] = $link[0]['href'].replace(qParam, qParam + encodeURIComponent(' #' + tags.join(' #')));
        });
    }

    // tag filter
    $ul = buildFilter(getTagsFromResults(), getFiltersFromQuery());
    $inputs = $ul.find('input');
    $inputs.change(function () {
        toggleTag(this.value);
    });
    $filterContainer.append($ul);

    // tags in other search filters
    addTagsToSearchLinks(jQuery('.advancedOptions a'));

});
