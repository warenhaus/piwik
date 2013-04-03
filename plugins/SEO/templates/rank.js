/*!
 * Piwik - Web Analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

$(document).ready(function() {
    $('#seo-ranks .row-evolution-link').hover(
        function() {
            $('img', this).each(function() {
                $(this).toggle();
            });
        }
    ).click(function(e) {
        e.preventDefault();
        DataTable_RowActions_RowEvolution.launch('SEO.getSEOStats', _pk_translate('SEO_Stats_js'));
        return false;
    });
    
    $('#check-site-seo-btn').click(function() {
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
    });
});

