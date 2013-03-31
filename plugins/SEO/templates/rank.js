/*!
 * Piwik - Web Analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

$(document).ready(function () {
    function getRank() {
        var ajaxRequest = new ajaxHelper();
        ajaxRequest.setLoadingElement('#ajaxLoadingSEO');
        ajaxRequest.addParams({
            module: 'SEO',
            action: 'getSEOStatsForUrl',
            url: encodeURIComponent($('#seoUrl').val())
        }, 'get');
        ajaxRequest.setCallback(
            function (response) {
                $('#seo-ranks').html(response);
            }
        );
        ajaxRequest.setFormat('html');
        ajaxRequest.send(false);
    }

    // click on Rank button
    $('#rankbutton').on('click', function () {
        getRank();
        return false;
    });
});
