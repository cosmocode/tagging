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
});
