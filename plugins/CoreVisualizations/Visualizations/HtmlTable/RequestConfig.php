<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package CoreVisualizations
 */

namespace Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable;

use Piwik\Common;
use Piwik\Config as PiwikConfig;
use Piwik\ViewDataTable\RequestConfig as VisualizationRequestConfig;

/**
 * DataTable Visualization that derives from HtmlTable and sets show_extra_columns to true.
 */
class RequestConfig extends VisualizationRequestConfig
{

    /**
     * Controls whether the summary row is displayed on every page of the datatable view or not.
     * If false, the summary row will be treated as the last row of the dataset and will only visible
     * when viewing the last rows.
     *
     * Default value: false
     */
    public $keep_summary_row = false;

    public function __construct()
    {
        $this->filter_limit = PiwikConfig::getInstance()->General['datatable_default_limit'];

        if (Common::getRequestVar('enable_filter_excludelowpop', false) == '1') {
            $this->filter_excludelowpop       = 'nb_visits';
            $this->filter_excludelowpop_value = false;
        }

        $this->addPropertiesThatShouldBeAvailableClientSide(array(
            'search_recursive',
            'filter_limit',
            'filter_offset',
            'filter_sort_column',
            'filter_sort_order',
            'keep_summary_row'
        ));

        $this->addPropertiesThatCanBeOverwrittenByQueryParams(array(
            'keep_summary_row',
        ));
    }

}